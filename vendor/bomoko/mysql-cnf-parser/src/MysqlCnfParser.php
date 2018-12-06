<?php

namespace bomoko\MysqlCnfParser;

use Nette\Utils\Finder;
use Webmozart\PathUtil\Path;

class MysqlCnfParser
{

    const FILE_TYPES = ['*.cnf', '*.ini'];

    protected $processedFiles = [];

    public static function parse($filename)
    {
        return (new self())->parseIniFile($filename);
    }

    public function parseIniFile($filename)
    {
        if (is_file($filename) && is_readable($filename)) {
            $this->markFileProcessed($filename);
            $contents = file_get_contents($filename);
            $contentArray = explode("\n", $contents);
            //go through the file and pop any "!include/!includedir" directives
            $toParse = [];
            $includes = [];
            foreach ($contentArray as $line) {
                if (strpos(trim($line), "!include") === 0) {
                    $includes[] = $line;
                } elseif (strlen($line) > 0 && !in_array($line[0],
                        ["!", "#"])
                ) { //ignore comments
                    $toParse[] = $line;
                }
            }

            return array_merge_recursive(
                parse_ini_string(implode("\n", $toParse), true),
                $this->processIncludes($includes,
                    Path::getDirectory($filename)));
        } else {
            return [];
        }
    }

    protected function processIncludes(Array $includes, $includePath)
    {
        $return = [];

        foreach ($includes as $include) {
            //strip any !include(s)
            $names = explode(" ", $include);
            $fileName = $names[1];
            $includeType = $names[0];

            $fileName = Path::makeAbsolute($fileName, $includePath);
            if (!$this->hasFileBeenProcessed($fileName)) {
                $this->markFileProcessed($fileName);
                $return = array_merge_recursive($return,
                    $includeType == "!includedir" ? $this->parseDirectory($fileName) : $this->parseIniFile($fileName));
            }
        }
        return $return;
    }

    protected function parseDirectory($directoryName)
    {
        $return = [];

        foreach (Finder::findFiles(self::FILE_TYPES)
                     ->in($directoryName) as $key => $file) {
            $return = array_merge_recursive($return, $this->parseIniFile($key));
        }

        return $return;
    }

    protected function hasFileBeenProcessed($fileName)
    {
        return in_array(Path::canonicalize($fileName), $this->processedFiles);
    }

    protected function markFileProcessed($fileName)
    {
        $canonicalFileName = Path::canonicalize($fileName);
        $this->processedFiles[$canonicalFileName] = $canonicalFileName;
    }
}
