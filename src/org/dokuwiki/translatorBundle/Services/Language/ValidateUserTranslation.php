<?php

namespace org\dokuwiki\translatorBundle\Services\Language;

class ValidateUserTranslation {

    private $defaultTranslation;
    private $previousTranslation;
    private $userTranslation;
    private $author;
    private $authorEmail;

    function __construct($defaultTranslation, $previousTranslation, array $userTranslation, $author, $authorEmail) {
        $this->defaultTranslation = $defaultTranslation;
        $this->userTranslation = $userTranslation;
        $this->previousTranslation = $previousTranslation;
        $this->author = $author;
        $this->authorEmail = $authorEmail;
    }

    
    function validate() {
        $newTranslation = array();

        /** @var LocalText $translation */
        foreach ($this->defaultTranslation as $path => $translation) {
            if (!isset($this->userTranslation[$path])) {
                continue;
            }

            if ($translation->getType() !== LocalText::$TYPE_ARRAY) {
                $newTranslation[$path] = $this->validateMarkup($path);
                continue;
            }


            $newTranslation[$path] = $this->validateArray($path, $translation);
        }

        return $newTranslation;
    }

    private function validateMarkup($path) {
        $text = $this->fixLineEndings($this->userTranslation[$path]);
        return new LocalText($text, LocalText::$TYPE_MARKUP);
    }

    private function validateArray($path, LocalText $translation) {
        $newContent = array();
        $translationChanged = false;

        $translationArray = $translation->getContent();
        foreach ($translationArray as $key => $text) {
            if (!isset($this->userTranslation[$path][$key])) {
                continue;
            }

            if ($key !== 'js') {
                $newContent[$key] = $this->fixLineEndings($this->userTranslation[$path][$key]);
                if ($this->hasTranslationChanged($path, $key, $translationChanged)) {
                    $translationChanged = true;
                }
                continue;
            }

            $newContent[$key] = array();
            foreach ($text as $jsKey => $jsVal) {
                if (!isset($this->userTranslation[$path][$key][$jsKey])) {
                    continue;
                }
                $newContent[$key][$jsKey] = $this->fixLineEndings($this->userTranslation[$path][$key][$jsKey]);
                if ($this->hasJsTranslationChanged($path, $key, $jsKey, $translationChanged)) {
                    $translationChanged = true;
                }
                continue;
            }
        }
        $authors = array();
        if (isset($this->previousTranslation[$path])) {
            /** @var LocalText $prevTranslation */
            $prevTranslation = $this->previousTranslation[$path];
            $authors = $prevTranslation->getAuthors();
        }

        if ($translationChanged && !empty($this->author)) {
            $authors[$this->author] = $this->authorEmail;
        }
        return new LocalText($newContent, LocalText::$TYPE_ARRAY, $authors);
    }

    private function hasTranslationChanged($path, $key, $alreadyChanged) {
        if ($alreadyChanged) return false;

        if (!isset($this->previousTranslation[$path])) {
            return $this->userTranslation[$path][$key] !== '';
        }

        /** @var LocalText $previous */
        $previous = $this->previousTranslation[$path];
        $previousText = $previous->getContent();

        if (!isset($previousText[$key])) {
            return $this->userTranslation[$path][$key] !== '';
        }
        return $this->userTranslation[$path][$key] !== $previousText[$key];
    }

    private function hasJsTranslationChanged($path, $key, $jsKey, $alreadyChanged) {
        if ($alreadyChanged) return false;

        if (!isset($this->previousTranslation[$path])) {
            return $this->userTranslation[$path][$key][$jsKey] !== '';
        }

        /** @var LocalText $previous */
        $previous = $this->previousTranslation[$path];
        $previousText = $previous->getContent();

        if (!isset($previousText[$key])) {
            return $this->userTranslation[$path][$key][$jsKey] !== '';
        }

        if (!isset($previousText[$key][$jsKey])) {
            return $this->userTranslation[$path][$key][$jsKey] !== '';
        }

        return $this->userTranslation[$path][$key][$jsKey] !== $previousText[$key][$jsKey];
    }


    private function fixLineEndings($string) {
        $string = str_replace("\r\n", "\n", $string);
        return $string;
    }


}