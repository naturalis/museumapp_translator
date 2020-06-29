<?php

class TTIKtranslator extends BaseClass
{
    private $languageCode_source;
    private $languageCode_target;

    private $translatorAPIUrl;
    private $translatorAPIUsageUrl;
    private $translatorAPIKey;
    private $maxRecords;

    private $selfTranslateNames=true;
    private $selfTranslatedtagName="ttik";

    private $untranslatedTexts=[];
    private $translatedHeaders=[];
    private $translatedTexts=[];

    const TABLE = 'ttik_translations';
    const TABLE_NAMES = 'ttik';

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

    public function initialize()
    {
        if (empty($this->translatorAPIUrl))
        {
            $this->log(sprintf("missing API URL"), 1, "ttik_translations");
            exit();
        }        

        if (empty($this->translatorAPIKey))
        {
            $this->log(sprintf("missing API key"), 1, "ttik_translations");
            exit();
        }        

        if (empty($this->translatorAPIUsageUrl))
        {
            $this->log(sprintf("missing API usage URL or API key"), 2, "ttik_translations");
        }        

        if (empty($this->languageCode_source) || empty($this->languageCode_target))
        {
            $this->log(sprintf("missing either source or target language"), 1, "ttik_translations");
            exit();
        }        

        if ($this->languageCode_source==$this->languageCode_target)
        {
            $this->log(sprintf("source and target language are the same"), 1, "ttik_translations");
            exit();
        }

        $this->log(sprintf("source language: %s; target language: %s", $this->languageCode_source,$this->languageCode_target), 3, "ttik_translations");

        if ($this->maxRecords>0)
        {
            $this->log(sprintf("max records: %s", $this->maxRecords), 3, "ttik_translations");            
        }
    }

    public function getUntranslatedTexts()
    {
        $this->connectDatabase();

        $result = $this->db->query("
            select
                source.*,
                names.dutch as dutch_names,
                names.english as english_name,
                names.taxon as taxon

            from 
                ".self::TABLE." source 
                left join ".self::TABLE." target 
                    on source.taxon_id = target.taxon_id 
                    and target.language_code = '".$this->languageCode_target."' 

                left join ".self::TABLE_NAMES." names 
                    on source.taxon_id = names.taxon_id 

            where 
                source.language_code='".$this->languageCode_source."' 
                and target.description is null
            " . ( $this->maxRecords > 0 ? "limit " . $this->maxRecords : "" ) ."
        ");

        while ($row = $result->fetch_array(MYSQLI_ASSOC))
        {
            $this->untranslatedTexts[]=$row;
        }

        $result = $this->db->query("
            select
                count(*) as total
            from 
                ".self::TABLE." source 
                left join ".self::TABLE." target 
                    on source.taxon_id = target.taxon_id 
                    and target.language_code = '".$this->languageCode_target."' 
            where 
                source.language_code='".$this->languageCode_source."' 
                and target.description is not null
        ");

        $row = $result->fetch_array(MYSQLI_ASSOC);

        $this->log(sprintf("fetched %s untranslated descriptions (found %s already translated records)",
            count($this->untranslatedTexts),$row['total']), 3, "ttik_translations");
    }

    public function translateTexts()
    {
        foreach ($this->untranslatedTexts as $val)
        {
            foreach (json_decode($val['description'],true) as $text)
            {
                if (empty($text['body']))
                {
                    $this->log(sprintf("empty body '%s' text for taxon id %s",
                        $text['title'],$val['taxon_id']));
                    continue;
                }

                // if (isset($this->translatedHeaders[$text['title']]))
                // {
                //     $translatedTitle = $this->translatedHeaders[$text['title']];
                // }
                // else
                // {
                //     $this->translatedHeaders[$text['title']] = $translatedTitle = 
                //         $this->html_entity_encode($this->doTranslateSingleText(html_entity_decode($text['title'])));
                // }

                if ($this->selfTranslateNames)
                {
                    $text['body'] = $this->selfTranslateName(
                        $text['body'],
                        $val['dutch_names'],
                        $val['english_name'],
                        $val['taxon']
                    );
                }

                $translatedBody = $this->html_entity_encode($this->doTranslateSingleText(html_entity_decode($text['body'])));

                if (!empty($translatedBody))
                {
                    if ($this->selfTranslateNames)
                    {
                        $translatedBody = 
                            str_replace(
                                [sprintf("<%s>",$this->selfTranslatedtagName), sprintf("</%s>",$this->selfTranslatedtagName)],
                                "",
                                $translatedBody
                            );
                    }

                    $this->translatedTexts[$val['taxon_id']][] = [
                        "title" => $translatedTitle,
                        "body" => $translatedBody,
                    ];
                }
                else
                {
                    $this->log(sprintf("couldn't translate '%s' text for taxon id %s",
                        $text['title'],$val['taxon_id']), 1, "ttik_translations");
                }
            }            
        }
    }

    public function printAPIUsage()
    {
        $data = $this->getAPIUsage();
        $this->log(sprintf("character count: %s (character limit: %s)",
            number_format($data["character_count"]),number_format($data["character_limit"])), 3, "ttik_translations");
    }

    public function storeTranslations()
    {
        foreach($this->translatedTexts as $taxon_id => $translations)
        {

            $stmt = $this->db->prepare("insert into ".self::TABLE." (language_code,description,taxon_id) values (?,?,?)");
            $stmt->bind_param('sss', $this->languageCode_target, json_encode($translations), $taxon_id);

            if ($stmt->execute())
            {
                $this->log(
                    sprintf("inserted translation '%s' for taxon_id %s",$this->languageCode_target, $taxon_id),3, "ttik_translations");
            } else {
                $this->log(
                    sprintf("could not insert translation '%s' for taxon_id %s",$this->languageCode_target, $taxon_id),1, "ttik_translations");
            }
        }
    }

    private function doTranslateSingleText($text)
    {
        $payload = [
          'auth_key' => $this->translatorAPIKey,
          'source_lang' => strtoupper($this->languageCode_source),
          'target_lang'  => strtoupper($this->languageCode_target),
          'preserve_formatting'  => '1',
          'tag_handling' => 'xml',
          'ignore_tags' => $this->selfTranslatedtagName,
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

            $this->log(sprintf("error getting usage: %s",$e->getMessage(),1, "ttik_translations"));

        }
    }

    private function html_entity_encode($str_in)
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

    private function getAPIUsage()
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
            $this->log(sprintf("error getting usage: %s",$e->getMessage(),1, "ttik_translations"));
        }
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

    private function selfTranslateName($text,$dutch_names,$english_name,$taxon)
    {
        try {

            $names_dutch = array_filter(json_decode($dutch_names,true),function($a)
            {
                var_dump($a);
                return $a["nametype"]=="isPreferredNameOf";
            });

            $dutchName = isset($names_dutch[0]) ? $names_dutch[0]['name'] : null;

            $names_english = array_filter(json_decode($english_name,true),function($a)
            {
                var_dump($a);
                return $a["nametype"]=="isPreferredNameOf";
            });

            $englishName = isset($names_english[0]) ? $names_english[0]['name'] : null;

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
                    "<%s>%s</%s>",
                    $this->selfTranslatedtagName,
                    $englishName,
                    $this->selfTranslatedtagName
                ),
                $text
            );

        } catch (Exception $e) {
            $this->log(sprintf("error self-translating name: %s",$e->getMessage(),1, "ttik_translations"));
            return $text;
        }
    }    
}
