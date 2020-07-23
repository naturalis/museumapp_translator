<?php

/*

    text is translated, but if we have dutch & english names ourselves:

    - dutch name is replaced with: 
        <remove_me>dutch name</remove_me> <ttik>english name</ttik>
    - API is told to ignore everything between <ttik>.
    - after translateion:
        <remove_me>dutch name</remove_me> is removed
        <ttik> and </ttik> are removed, leaving our own english translation
    - the <remove_me>dutch name</remove_me> is required to retain an actual synrtactic subject in the sentence.
      otherwise 
        De <ttik>ocean sunfish</ttik> is groot.
      becomes
        It <ttik>ocean sunfish</ttik> is big.
      rather than
        The <ttik>ocean sunfish</ttik> is big.

*/

class TTIKtranslator extends TranslatorBaseClass
{
    private $ttik_project_id;
    private $ttik_language_ids=[];
    private $ttik_page_ids=[];
    private $translatedHeaders=[];

    const TABLE = 'ttik_translations';
    const TABLE_NAMES = 'ttik';

    public function __construct ()
    {
        $this->setTranslateTitles(false);
    }

    public function setTtikProjectId( $ttik_project_id )
    {
        $this->ttik_project_id = $ttik_project_id;
    }

    public function setTtikLanguageIds( $ttik_language_ids )
    {
        $this->ttik_language_ids = $ttik_language_ids;
    }

    public function setTtikPageIds( $ttik_page_ids )
    {
        $this->ttik_page_ids = $ttik_page_ids;
    }

    public function setTranslateTitles( $state )
    {
        if (is_bool($state))
        {
            $this->translateTitles = $state;
        }
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
                left join ".self::TABLE." target 
                    on source.taxon_id = target.taxon_id 
                    and target.language_code = '".$this->languageCode_target."' 

                left join ".self::TABLE_NAMES." names 
                    on source.taxon_id = names.taxon_id 

            where 
                source.language_code='".$this->languageCode_source."' 
                and target.description is null
            " . ( $this->maxRecords > 0 ? "limit " . $this->maxRecords : "" ) ."
        ";

        if ($this->debug)
        {
            print_r($query);
        }

        try {

            $result = $this->db->query($query);

            while ($row = $result->fetch_array(MYSQLI_ASSOC))
            {
                $this->untranslatedTexts[]=$row;
            }

        } catch (Exception $e) {

            $this->log(sprintf("error getting untranslated texts: %s",$e->getMessage(),1, "ttik_translations"));
        }

        $query = "
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
        ";

        if ($this->debug)
        {
            print_r($query);
        }

        try {

            $result = $this->db->query($query);
            $row = $result->fetch_array(MYSQLI_ASSOC);
            $total=$row['total'];

        } catch (Exception $e) {

            $this->log(sprintf("error getting untranslated texts: %s",$e->getMessage(),1, "ttik_translations"));

        }

        $this->log(sprintf("fetched %s untranslated descriptions (found %s already translated records)",
            count($this->untranslatedTexts),$total), 3, "ttik_translations");
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

                if ($this->translateTitles)
                {
                    if (isset($this->translatedHeaders[$text['title']]))
                    {
                        $translatedTitle = $this->translatedHeaders[$text['title']];
                    }
                    else
                    {
                        $this->translatedHeaders[$text['title']] = $translatedTitle = 
                            $this->html_entity_encode($this->doTranslateSingleText(html_entity_decode($text['title'])));
                    }
                }
                else
                {
                    $translatedTitle = $text['title'];
                }

                if ($this->debug)
                {
                    print_r($text['body']);
                }

                if ($this->selfTranslateNames)
                {
                    $text['body'] = $this->selfTranslateName(
                        $text['body'],
                        $val['dutch_names'],
                        $val['english_names'],
                        $val['taxon']
                    );
                }

                if ($this->debug)
                {
                    print_r($text['body']);
                }

                $translatedBody = $this->html_entity_encode($this->doTranslateSingleText(html_entity_decode($text['body'])));

                if ($this->debug)
                {
                    print_r($translatedBody);
                }

                if (!empty($translatedBody))
                {
                    if ($this->selfTranslateNames)
                    {
                        $translatedBody = 
                            preg_replace([
                                "/(<".$this->selfTranslatedTagNameOriginal.">[^<]*<\/".$this->selfTranslatedTagNameOriginal.">)/",
                                "/(<[\/]?".$this->selfTranslatedTagName.">)/",
                                "/(\s)+/"
                            ],[" "," "," "],$translatedBody);
                    }

                    $this->translatedTexts[$val['taxon_id']][] =
                    [
                        "title" => $translatedTitle,
                        "body" => $translatedBody
                    ];

                    $this->log(sprintf("fetched '%s' translation for %s",
                        $text['title'],$val['taxon']), 3, "translator base class");                    
                }
                else
                {
                    $this->log(sprintf("couldn't translate '%s' for taxon %s",
                        $text['title'],$val['taxon']), 1, "translator base class");
                }

                if ($this->debug)
                {
                    print_r($translatedBody);
                }                
            }            
        }
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

    public function doExport()
    {
        $this->setTranslatedTextsTable(self::TABLE);
        $this->getTranslatedTexts();
        
        $i=0;
        $fp = fopen($this->exportOutfile, "w");
        fputcsv($fp, [ "project_id", "taxon_id", "language_id", "page_id", "content" ]);
        foreach ($this->translatedTexts as $record)
        {
            foreach (json_decode($record["description"],true) as $line)
            {
                if (!isset($this->ttik_page_ids[$line["title"]]))
                {
                    $this->log(sprintf("unknown title: %s", $line["title"]), 1, "ttik_translations");
                }
                else
                if (!isset($this->ttik_language_ids[$this->exportLanguage]))
                {
                    $this->log(sprintf("unknown language: %s", $this->exportLanguage), 1, "ttik_translations");
                }
                else
                {    
                    fputcsv($fp, [
                        $this->ttik_project_id,
                        $record["taxon_id"],
                        $this->ttik_language_ids[$this->exportLanguage],
                        $this->ttik_page_ids[$line["title"]],
                        trim($line["body"])
                    ]);
                    $i++;
                }
            }
        }
        fclose($fp);

        $this->log(sprintf("exported %s lines to %s", $i, $this->exportOutfile), 3, "ttik_translations");
    }

}
