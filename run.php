<?php

    $db["host"] = getEnv("MYSQL_HOST");
    $db["user"] = getEnv("MYSQL_USER");
    $db["pass"] = getEnv("MYSQL_PASSWORD");
    $db["database"] = getEnv("MYSQL_DATABASE");

    $translatorAPIUrl = getEnv("TRANSLATOR_API_URL");
    $translatorAPIUsageUrl = getEnv("TRANSLATOR_API_USAGE_URL");
    $translatorAPIKey = getEnv("TRANSLATOR_API_KEY");
    $maxRecords = getEnv("TRANSLATOR_MAX_RECORDS");

    $maxRecords = is_numeric($maxRecords) ? intval($maxRecords) : 0;

    include_once("class.baseClass.php");
    include_once("class.ttikTranslator.php");

    $n = new TTIKtranslator;

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
