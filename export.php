<?php

    $opt = getopt("",["source:", "outfile:"]);
    $exportPath = rtrim(getEnv("TRANSLATOR_EXPORT_PATH"),"/");

    if (!isset($opt["outfile"]))
    {
        echo "need an CSV-outfile\n";
        echo "usage: php export --outfile=<file> --source=[ttik|topstukken]\n";
        echo "export path: ", $exportPath, "\n";
        exit(0);
    }
    else
    {
        $outfile = ltrim($opt["outfile"], "/");
    }

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

    include_once("class.baseClass.php");
    include_once("class.translatorBaseClass.php");

    switch ($source)
    {
        case "ttik":
            include_once("class.ttikTranslator.php");

            $n = new TTIKtranslator;

            $n->setDatabaseCredentials( $db );
            $n->setExportLanguage( "en" );
            $n->setExportOutfile( $exportPath . "/" . $outfile );

            $n->setTtikProjectId( 1 );
            $n->setTtikLanguageIds( [ "nl" => 24, "en" => 26 ] );
            $n->setTtikPageIds( [ "Beschrijving" => 1, "Leefperiode" => 4, "Leefgebied" => 5 ] );

            $n->doExport();

            break;

        case "topstukken":
            include_once("class.topstukkenTranslator.php");

            $n = new TopstukkenTranslator;

            $n->setDatabaseCredentials( $db );
            $n->setExportLanguage( "en" );
            $n->setExportOutfile( $exportPath . "/" . $outfile );

            $n->doExport();

            break;

        default:
            echo sprintf("error: unknown source '%s'\n",$source);
    }
