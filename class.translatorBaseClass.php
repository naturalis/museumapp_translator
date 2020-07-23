<?php

class TranslatorBaseClass extends BaseClass
{
    public $languageCode_source;
    public $languageCode_target;
    public $maxRecords;
    public $translateTitles;
    public $selfTranslateNames=true;
    public $selfTranslatedTagName="keep_me";
    public $selfTranslatedTagNameOriginal="remove_me";
    public $untranslatedTexts=[];
    public $translatedTexts=[];
    public $exportLanguage;
    public $exportOutfile;
    public $debug=false;
    private $translatedTextsTable;

    private $translatorAPIUrl;
    private $translatorAPIUsageUrl;
    private $translatorAPIKey;

    public function setSourceAndTargetLanguage( $source, $target )
    {
        $this->languageCode_source = $source;
        $this->languageCode_target = $target;
    }

    public function setTranslatorAPIUrl( $translatorAPIUrl )
    {
        $this->translatorAPIUrl = $translatorAPIUrl;
    }
    public function setTranslatorAPIUsageUrl( $translatorAPIUsageUrl )
    {
        $this->translatorAPIUsageUrl = $translatorAPIUsageUrl;
    }

    public function setTranslatorAPIKey( $translatorAPIKey )
    {
        $this->translatorAPIKey = $translatorAPIKey;
    }

    public function setSelfTranslateNames( $selfTranslateNames )
    {
        if (is_bool($selfTranslateNames))
        {
            $this->selfTranslateNames = $selfTranslateNames;
        }
    }

    public function setTranslatorMaxRecords( $maxRecords )
    {
        if (is_integer($maxRecords))
        {
            $this->maxRecords = $maxRecords;
        }
        else
        {
            throw new Exception(sprintf("%s is not an integer",$maxRecords), 1);
        }
    }

    public function setDebug( $state )
    {
        if (is_bool($state))
        {
            $this->debug = $state;
        }
    }

    public function initialize()
    {
        if (empty($this->translatorAPIUrl))
        {
            $this->log(sprintf("missing API URL"), 1, "translator_base_class");
            exit();
        }        

        if (empty($this->translatorAPIKey))
        {
            $this->log(sprintf("missing API key"), 1, "translator_base_class");
            exit();
        }        

        if (empty($this->translatorAPIUsageUrl))
        {
            $this->log(sprintf("missing API usage URL or API key"), 2, "translator_base_class");
        }        

        if (empty($this->languageCode_source) || empty($this->languageCode_target))
        {
            $this->log(sprintf("missing either source or target language"), 1, "translator_base_class");
            exit();
        }        

        if ($this->languageCode_source==$this->languageCode_target)
        {
            $this->log(sprintf("source and target language are the same"), 1, "translator_base_class");
            exit();
        }

        $this->log(sprintf("source language: %s; target language: %s", $this->languageCode_source,$this->languageCode_target), 3, "translator_base_class");

        if ($this->maxRecords>0)
        {
            $this->log(sprintf("max records: %s", $this->maxRecords), 3, "translator_base_class");
        }

        $this->log(sprintf("self-translating vernacular names (if available): %s",
            $this->selfTranslateNames ? 'y' : 'n' ), 3, "translator_base_class");

        $this->log(sprintf("debug: %s",
            $this->debug ? 'y' : 'n' ), 3, "translator_base_class");
    }

    public function printAPIUsage()
    {
        $data = $this->getAPIUsage();
        $this->log(sprintf("current API usage; character count: %s (character limit: %s)",
            number_format($data["character_count"]),number_format($data["character_limit"])), 3, "translator_base_class");
    }

    public function setExportLanguage( $export_language )
    {
        $this->exportLanguage = $export_language;
    }

    public function setExportOutfile( $export_outfile )
    {
        $this->exportOutfile = $export_outfile;
    }

    public function doTranslateSingleText($text)
    {
        $payload = [
          'auth_key' => $this->translatorAPIKey,
          'source_lang' => strtoupper($this->languageCode_source),
          'target_lang'  => strtoupper($this->languageCode_target),
          'preserve_formatting'  => '1',
          'tag_handling' => 'xml',
          'ignore_tags' => $this->selfTranslatedTagName,
          'text' => $text
        ];

        try {

            $raw = $this->doCurlRequest($this->translatorAPIUrl,$payload);
            // {
            //     "translations": [{
            //         "detected_source_language":"EN",
            //         "text":"Hallo, Welt!"
            //     }]
            // }

            $data = json_decode($raw,true);

            return isset($data['translations']) && isset($data['translations'][0]) ? $data['translations'][0]['text'] : null;

        } catch (Exception $e) {

            $this->log(sprintf("error getting usage: %s",$e->getMessage(),1, "translator_base_class"));

        }
    }

    public function html_entity_encode($str_in)
    {
        $list = get_html_translation_table(HTML_ENTITIES);
        unset($list['"']);
        unset($list['<']);
        unset($list['>']);
        unset($list['&']);

        $search = array_keys($list);
        $values = array_values($list);
        // $search = array_map('utf8_encode', $search);
        $str_out = str_replace($search, $values, $str_in);
        return $str_out;
    }

    public function getAPIUsage()
    {
        try {
            $payload = ['auth_key' => $this->translatorAPIKey];
            $raw = $this->doCurlRequest($this->translatorAPIUsageUrl,$payload);           
            // {
            //     "character_count": 180118,
            //     "character_limit": 1250000
            // }

            return json_decode($raw,true);
        } catch (Exception $e) {
            $this->log(sprintf("error getting usage: %s",$e->getMessage(),1, "translator_base_class"));
        }
    }

    public function selfTranslateName($text,$dutch_names,$english_names,$taxon)
    {
        try {

            $tmp = array_values(array_filter((array)json_decode($dutch_names,true),function($a)
            {
                return $a["nametype"]=="isPreferredNameOf";
            }));

            $dutchName = isset($tmp[0]) ? $tmp[0]['name'] : null;


            $tmp = array_values(array_filter((array)json_decode($english_names,true),function($a)
            {
                return $a["nametype"]=="isPreferredNameOf";
            }));

            $englishName = isset($tmp[0]) ? $tmp[0]['name'] : null;

            if (is_null($dutchName))
            {
                throw new Exception(sprintf("missing dutch name for %s",$taxon), 1);
            }

            if (is_null($englishName))
            {
                throw new Exception(sprintf("missing english name for %s",$taxon), 1);
            }

            return str_replace(
                $dutchName,
                sprintf(
                    "<%s>%s</%s> <%s>%s</%s>",
                    $this->selfTranslatedTagNameOriginal,
                    $dutchName,
                    $this->selfTranslatedTagNameOriginal,
                    $this->selfTranslatedTagName,
                    $englishName,
                    $this->selfTranslatedTagName
                ),
                $text
            );

        } catch (Exception $e) {
            $this->log(sprintf("error self-translating name: %s",$e->getMessage()),1, "translator_base_class");
            return $text;
        }
    }    

    public function setTranslatedTextsTable($table)
    {
        $this->translatedTextsTable = $table;
    }

    public function getTranslatedTexts($verified_state=false)
    {

        if (empty($this->translatedTextsTable))
        {
            throw new Exception("no table with translated texts specified", 1);
        }

        $this->connectDatabase();

        $result = $this->db->query("
            select
                *
            from 
                " . $this->translatedTextsTable . "
            where 
                language_code='" . $this->exportLanguage . "' 
                and description is not null
                and verified = " . ($verified_state ? "1" : "0")
        );

        while ($row = $result->fetch_array(MYSQLI_ASSOC))
        {
            $this->translatedTexts[]=$row;
        }

        $this->log(sprintf("fetched %s unverified translated records", count($this->translatedTexts)), 3, "translator_base_class");
    }

    private function doCurlRequest($url,$payload)
    {
        if(empty($url))
        {
            throw new Exception("empty URL");
        }

        $handle = curl_init($url);

        curl_setopt_array(
            $handle,
            [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_POSTFIELDS => $payload,
                CURLOPT_RETURNTRANSFER => true,
            ]
        );
 
        $data = curl_exec($handle);

        if($data===false)
        {
            throw new Exception("cURL error: " . curl_error($handle));
        }

        curl_close($handle);

        return $data;
    }
 
}
