<?php

    $opt = getopt("",["source:"]);

    if (isset($opt["source"]))
    {
        $source = $opt["source"];
    }
    else
    {
        throw new Exception("no source specified (use: --source=[ttik|topstukken]>)", 1);
    }

    $db["host"] = getEnv("MYSQL_HOST");
    $db["user"] = getEnv("MYSQL_USER");
    $db["pass"] = getEnv("MYSQL_PASSWORD");
    $db["database"] = getEnv("MYSQL_DATABASE");

    $translatorAPIUrl = getEnv("TRANSLATOR_API_URL");
    $translatorAPIUsageUrl = getEnv("TRANSLATOR_API_USAGE_URL");
    $translatorAPIKey = getEnv("TRANSLATOR_API_KEY");
    $maxRecords = getEnv("TRANSLATOR_MAX_RECORDS");
    $debug = getEnv("TRANSLATOR_DEBUG")=="1";

    $maxRecords = is_numeric($maxRecords) ? intval($maxRecords) : 0;

    include_once("class.baseClass.php");
    include_once("class.translatorBaseClass.php");

    switch ($source)
    {
        case "ttik":

            include_once("class.ttikTranslator.php");

            $n = new TTIKtranslator;

            $n->setDebug( $debug );
            $n->setDatabaseCredentials( $db );
            $n->setSourceAndTargetLanguage( 'nl','en');
            $n->setTranslatorAPIUrl( $translatorAPIUrl );
            $n->setTranslatorAPIUsageUrl( $translatorAPIUsageUrl );
            $n->setTranslatorAPIKey( $translatorAPIKey );
            $n->setTranslatorMaxRecords( $maxRecords );
            // $n->setTranslateTitles( true );

            $n->initialize();

            $n->getUntranslatedTexts();
            $n->translateTexts();
            $n->storeTranslations();
            $n->printAPIUsage();

            break;

        case "topstukken":

            include_once("class.topstukkenTranslator.php");

            $n = new TopstukkenTranslator;

            $n->setDebug( $debug );
            $n->setDatabaseCredentials( $db );
            $n->setSourceAndTargetLanguage( 'nl','en');
            $n->setTranslatorAPIUrl( $translatorAPIUrl );
            $n->setTranslatorAPIUsageUrl( $translatorAPIUsageUrl );
            $n->setTranslatorAPIKey( $translatorAPIKey );
            $n->setTranslatorMaxRecords( $maxRecords );

            $n->initialize();

            $n->getUntranslatedTexts();
            $n->translateTexts();
            $n->storeTranslations();
            $n->printAPIUsage();

            break;

        default:
            echo sprintf("error: unknown source '%s'\n",$source);
    }
