<?php

namespace org\dokuwiki\translatorBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use org\dokuwiki\translatorBundle\Services\Language\LocalText;

class TranslationController extends Controller {

    public function translateCoreAction() {
        $entityManager = $this->getDoctrine()->getManager();

        $language = $this->get('language_manager')->getLanguage($this->getRequest());
        $repositoryEntity = $entityManager->getRepository('dokuwikiTranslatorBundle:RepositoryEntity')
            ->getCoreRepository();


        $data['name'] = $repositoryEntity->getDisplayName();

        $data['translations'] = $this->prepareLanguages($language, $repositoryEntity);

        $data['targetLanguageName'] = $entityManager->getRepository('dokuwikiTranslatorBundle:LanguageNameEntity')
            ->getLanguageNameByCode($language);


        return $this->render('dokuwikiTranslatorBundle:Translate:translate.html.twig',
                $data);
    }

    private function prepareLanguages($language, $repositoryEntity) {
        $repositoryManager = $this->get('repository_manager');
        $repository = $repositoryManager->getRepository($repositoryEntity);

        $defaultTranslation = $repository->getLanguage('en');
        $targetTranslation = $repository->getLanguage($language);

        $missingTranslations = array();
        $availableTranslations = array();


        foreach ($defaultTranslation as $path => $translation) {
            if ($translation->getType() !== LocalText::$TYPE_ARRAY) {
                $entry = $this->createEntry($defaultTranslation, $targetTranslation, $path);

                if (empty($entry['target'])) {
                    $missingTranslations[] = $entry;
                    continue;
                }
                $availableTranslations[] = $entry;
                continue;
            }
            $translationArray = $translation->getContent();
            foreach ($translationArray as $key => $text) {
                if ($key !== 'js') {
                    $entry = $this->createEntry($defaultTranslation, $targetTranslation, $path, $key);

                    if (empty($entry['target'])) {
                        $missingTranslations[] = $entry;
                        continue;
                    }
                    $availableTranslations[] = $entry;
                    continue;
                }
                foreach ($text as $jsKey => $jsVal) {
                    $entry = $this->createEntry($defaultTranslation, $targetTranslation, $path, $key, $jsKey);

                    if (empty($entry['target'])) {
                        $missingTranslations[] = $entry;
                        continue;
                    }
                    $availableTranslations[] = $entry;
                    continue;
                }
            }
        }

        return array_merge($missingTranslations, $availableTranslations);
    }

    private function createEntry($defaultTranslation, $targetTranslation, $path, $key = null, $jsKey = null) {
        $entry = array();
        $entry['key'] = $this->createEntryKey($path, $key, $jsKey);
        $entry['default'] = $this->createEntryGetTranslation($defaultTranslation, $path, $key, $jsKey);
        $entry['target'] = $this->createEntryGetTranslation($targetTranslation, $path, $key, $jsKey);
        $entry['type'] = ($key === null) ? LocalText::$TYPE_MARKUP : LocalText::$TYPE_ARRAY;
        return $entry;
    }

    function createEntryGetTranslation($translation, $path, $key = null, $jsKey = null) {
        if (!isset($translation[$path])) {
            return '';
        }
        if ($key === null) return $translation[$path]->getContent();

        $translation = $translation[$path]->getContent();
        if (!isset($translation[$key])) return '';
        if ($jsKey === null) return $translation[$key];

        if (!isset($translation[$key][$jsKey])) return '';
        return $translation[$key][$jsKey];
    }

    function createEntryKey($path, $key = null, $jsKey = null) {
        $entryKey = sprintf('translation[%s]', urlencode($path));
        if ($key === null) return $entryKey;

        $entryKey .= sprintf('[%s]', urlencode($key));
        if ($jsKey === null) return $entryKey;

        $entryKey .= sprintf('[%s]', urlencode($jsKey));
        return $entryKey;
    }

    public function translatePluginAction($name) {
        return $this->render('dokuwikiTranslatorBundle:Translate:translate.html.twig',
                array('name' => $name));
    }

}
