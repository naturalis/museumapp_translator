<?php

class TopstukkenTranslator extends TranslatorBaseClass
{
    const TABLE = 'topstukken';
    const TABLE_TRANSLATIONS = 'topstukken_translations';
    const TABLE_NAMES = 'ttik';

    public function __construct ()
    {
    }

    public function getUntranslatedTexts()
    {
        $this->connectDatabase();

        $query = "
            select
                source.*,
                names.dutch as dutch_names,
                names.english as english_names,
                names.taxon as taxon

            from 
                ".self::TABLE." source 
                left join ".self::TABLE_TRANSLATIONS." target 
                    on source.registrationNumber = target.registrationNumber 
                    and target.language_code = '".$this->languageCode_target."' 

                left join ".self::TABLE_NAMES." names 
                    on concat(
                        names.uninomial,' ',
                        names.specific_epithet,' ',
                        ifnull(names.infra_specific_epithet,'')
                    ) = source.scientificName

            where 
                target.description is null
            " . ( $this->maxRecords > 0 ? "limit " . $this->maxRecords : "" ) ."
        ";

        if ($this->debug)
        {
            // print_r($query);
        }

        try {

            $result = $this->db->query($query);

            while ($row = $result->fetch_array(MYSQLI_ASSOC))
            {
                $this->untranslatedTexts[]=$row;
            }

        } catch (Exception $e) {

            $this->log(sprintf("error getting untranslated texts: %s",$e->getMessage(),1, "topstukken_translations"));
        }

        $query = "
            select
                count(*) as total
            from 
                ".self::TABLE." source 
                left join ".self::TABLE_TRANSLATIONS." target 
                    on source.registrationNumber = target.registrationNumber 
                    and target.language_code = '".$this->languageCode_target."' 
            where 
                target.description is not null
        ";

        if ($this->debug)
        {
            // print_r($query);
        }

        try {

            $result = $this->db->query($query);
            $row = $result->fetch_array(MYSQLI_ASSOC);
            $total=$row['total'];

        } catch (Exception $e) {

            $this->log(sprintf("error getting untranslated texts: %s",$e->getMessage(),1, "topstukken_translations"));

        }

        $this->log(sprintf("fetched %s untranslated descriptions (found %s already translated records)",
            count($this->untranslatedTexts),$total), 3, "topstukken_translations");
    }

    public function translateTexts()
    {
        foreach ($this->untranslatedTexts as $val)
        {
            $taxonTranslations = [];

            foreach (json_decode($val['description'],true) as $paragraph)
            {
                $translations=[];

                foreach (["title","body"] as $i => $element)
                {
                    $paraElement = $paragraph[$i];

                    if (isset($paraElement) && !empty(trim($paraElement)))
                    {
                        if ($this->debug)
                        {
                            var_dump($element);
                            var_dump($paraElement);
                        }

                        if ($this->selfTranslateNames)
                        {
                            $untrnsltd = $this->selfTranslateName(
                                $paraElement,
                                $val['dutch_names'],
                                $val['english_names'],
                                $val['scientificName']
                            );
                        }
                        else
                        {
                            $untrnsltd = $paraElement;
                        }

                        if ($this->debug)
                        {
                            var_dump($untrnsltd);
                        }
            
                        $transltd = $this->html_entity_encode($this->doTranslateSingleText(html_entity_decode($untrnsltd)));

                        if ($this->debug)
                        {
                            var_dump($transltd);
                        }

                        if (empty($transltd))
                        {
                            $this->log(sprintf("no translation for %s '%s'", $element,$untrnsltd), 3, "topstukken_translations");
                        }
                        else
                        {
                            if ($this->selfTranslateNames)
                            {
                                $transltd = 
                                    preg_replace([
                                        "/(<".$this->selfTranslatedTagNameOriginal.">[^<]*<\/".$this->selfTranslatedTagNameOriginal.">)/",
                                        "/(<[\/]?".$this->selfTranslatedTagName.">)/",
                                        "/(\s)+/"
                                    ],[" "," "," "],$transltd);
                            }                            
                        }

                        if ($this->debug)
                        {
                            var_dump($transltd);
                        }

                        $translations[$i]=$transltd;
                    }
                }

                $taxonTranslations[] = $translations;
            }

            $this->translatedTexts[$val['registrationNumber']] = [
                "scientificName" => $val['scientificName'],
                "translations" => $taxonTranslations
            ];

            $this->log(sprintf("translated content for %s (%s)", $val['scientificName'], $val['registrationNumber']), 3,
                "topstukken_translations");
        }
    }

    public function storeTranslations()
    {
        foreach($this->translatedTexts as $registrationNumber => $content)
        {
            $scientificName = $content["scientificName"];
            $translations = $content["translations"];

            $stmt = $this->db->prepare("insert into ".self::TABLE_TRANSLATIONS.
                " (registrationNumber,language_code,description) values (?,?,?)");
            $stmt->bind_param('sss', $registrationNumber, $this->languageCode_target, json_encode($translations));

            if ($stmt->execute())
            {
                $this->log(
                    sprintf("inserted translation '%s' for %s",$this->languageCode_target, $scientificName), 3,
                        "topstukken_translations");
            } else {
                $this->log(
                    sprintf("could not insert translation '%s' for %s",$this->languageCode_target, $scientificName), 1,
                        "topstukken_translations");
            }
        }
    }

    public function doExport()
    {
        $this->getTranslatedTexts(self::TABLE_TRANSLATIONS);
        
        $i=0;
        $fp = fopen($this->exportOutfile, "w");

        fputcsv($fp, [ "registrationNumber", "language_code", "page_title", "paragraph_title", "paragraph_body" ]);

        foreach ($this->translatedTexts as $record)
        {
            foreach(json_decode($record["description"]) as $index => $paragraph)
            {
                fputcsv($fp, [
                    ($index==0 ? $record["registrationNumber"] : ''),
                    ($index==0 ? $record["language_code"] : ''),
                    ($index==0 ? $record["title"] : ''),
                    strip_tags($paragraph[0]),
                    strip_tags($paragraph[1])
                ]);                

                $i++;
            }

        }

        fclose($fp);

        $this->log(sprintf("exported %s lines to %s", $i, $this->exportOutfile), 3, "topstukken_translations");
    }

}
