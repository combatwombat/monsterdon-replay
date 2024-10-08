<?php

namespace RTF;

class DocumentFileDB {

    public $collectionsFolder;

    public $collectionsCache;

    public $documentsCache;

    function __construct($collectionsFolder = null) {
        if (empty($collectionsFolder)) {
            $collectionsFolder = getcwd() . '/../content/';
        }
        $this->collectionsFolder = $collectionsFolder;
    }

    /**
     * Loads a collection (all .md documents in a content/$name folder). Parses the JSON inside the frontmatter, optionally gets the markdown below. Caches it.
     * @param $name
     * @return array
     */
    public function getCollection($name, $withMarkdown = true) {

        if (!empty($this->collectionsCache[$name . "_" . $withMarkdown])) {
            return $this->collectionsCache[$name . "_" . $withMarkdown];
        }

        $collection = [];
        $collectionFolder = $this->collectionsFolder . $name;
        if (is_dir($collectionFolder)) {
            $files = scandir($collectionFolder);
            foreach ($files as $file) {
                if (strpos($file, '.md') !== false) {
                    $collection[] = $this->getDocument($name, $file, $withMarkdown);
                }
            }
        } else {
            throw new \Exception('Collection not found: ' . $collectionFolder);
        }

        $this->collectionsCache[$name . "_" . $withMarkdown] = $collection;
        return $collection;
    }

    /**
     * Get a single document, extract json from frontmatter, optionally get markdown below. cache it
     * @param $collectionName
     * @param $fileName
     * @param $withMarkdown
     * @return array
     */
    public function getDocument($collectionName, $fileName, $withMarkdown = true) {

        // add .md to end of filename if not there
        if (strpos($fileName, '.md') === false) {
            $fileName .= '.md';
        }

        if (!empty($this->documentsCache[$collectionName][$fileName . "_" . $withMarkdown])) {
            return $this->documentsCache[$collectionName][$fileName . "_" . $withMarkdown];
        }

        $document = [];
        $document['id'] = $fileName;
        $document['collection'] = $collectionName;

        if (!file_exists($this->collectionsFolder . $collectionName . '/' . $fileName)) {
            throw new \Exception('File not found: ' . $this->collectionsFolder . $collectionName . '/' . $fileName);
        }

        $fileData = file_get_contents($this->collectionsFolder . $collectionName . '/' . $fileName);
        $fileData = explode('+++', $fileData);
        $document['data'] = json_decode($fileData[1], true);

        if ($document['data'] === null) {
            throw new \Exception('Invalid JSON in frontmatter: ' . $this->collectionsFolder . $collectionName . '/' . $fileName);
        }

        $document['content'] = '';
        if ($withMarkdown) {
            $document['content'] = trim($fileData[2]);
        }

        $this->documentsCache[$collectionName][$fileName . "_" . $withMarkdown] = $document;

        return $document;
    }

    /**
     * Save document
     * @param $collectionName
     * @param $fileName
     * @param $doc
     * @return void
     */
    public function setDocument($collectionName, $fileName, $doc) {
            if (strpos($fileName, '.md') === false) {
                $fileName .= '.md';
            }

            $fileData = "+++\n";
            $fileData .= json_encode($doc['data'], JSON_PRETTY_PRINT);
            $fileData .= "\n+++\n";
            $fileData .= $doc['content'];

            file_put_contents($this->collectionsFolder . $collectionName . '/' . $fileName, $fileData);

            $this->documentsCache[$collectionName][$fileName . "_" . true] = $doc;
            $this->documentsCache[$collectionName][$fileName . "_" . false] = $doc;

            return $doc;
    }



    /**
     * Convert columnName to column_name
     * @param string $str cameCaseString
     * @return string underscore_string
     */
    public function camelCaseToUnderscores($str) {
        preg_match_all('!([A-Z][A-Z0-9]*(?=$|[A-Z][a-z0-9])|[A-Za-z][a-z0-9]+)!', $str, $matches);
        $ret = $matches[0];
        foreach ($ret as &$match) {
            $match = $match == strtoupper($match) ? strtolower($match) : lcfirst($match);
        }
        return implode('_', $ret);
    }
}