<?php

namespace org\dokuwiki\translatorBundle\Controller;

use Doctrine\ORM\EntityManager;
use Doctrine\ORM\NoResultException;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use org\dokuwiki\translatorBundle\Entity\RepositoryEntity;
use org\dokuwiki\translatorBundle\Form\RepositoryCreateType;
use org\dokuwiki\translatorBundle\Services\Repository\Repository;

class PluginController extends Controller {

    public function indexAction(Request $request) {

        $data = array();

        $repository = new RepositoryEntity();
        $repository->setEmail('');
        $repository->setUrl('');
        $repository->setBranch('master');

        $form = $this->createForm(new RepositoryCreateType(), $repository);

        if ($request->isMethod('POST')) {
            $form->bind($request);
            if ($form->isValid()) {
                $this->addPlugin($repository);
                $data['repository'] = $repository;
                return $this->render('dokuwikiTranslatorBundle:Plugin:added.html.twig', $data);
            }
        }

        $data['form'] = $form->createView();

        return $this->render('dokuwikiTranslatorBundle:Plugin:add.html.twig', $data);
    }

    private function addPlugin(RepositoryEntity &$repository) {
        $api = $this->get('doku_wiki_repository_api');

        $api->mergePluginInfo($repository);
        $repository->setLastUpdate(0);
        $repository->setState(RepositoryEntity::$STATE_WAITING_FOR_APPROVAL);
        $repository->setActivationKey($this->generateActivationKey($repository));
        $entityManager = $this->getDoctrine()->getManager();
        $entityManager->persist($repository);
        $entityManager->flush();

        $message = \Swift_Message::newInstance();
        $message->setSubject('Registration');
        $message->setTo($repository->getEmail());
        $message->setFrom($this->container->getParameter('mailer_from'));
        $data = array(
            'repository' => $repository,
        );
        $message->setBody($this->renderView('dokuwikiTranslatorBundle:Mail:pluginAdded.txt.twig', $data));
        $this->get('mailer')->send($message);
    }

    private function generateActivationKey(RepositoryEntity $repository) {
        return md5($repository->getName() . time());
    }

    public function activateAction($name, $key) {
        /**
         * @var $entityManager EntityManager
         */
        $entityManager = $this->getDoctrine()->getManager();
        $query = $entityManager->createQuery(
            'SELECT repository
             FROM dokuwikiTranslatorBundle:RepositoryEntity repository
             WHERE repository.name = :name
             AND repository.activationKey = :key
             AND repository.state = :state'
        );
        $query->setParameter('name', $name);
        $query->setParameter('key', $key);
        $query->setParameter('state', RepositoryEntity::$STATE_WAITING_FOR_APPROVAL);
        try {
            $repository = $query->getSingleResult();
            $this->activateRepository($repository);
            $entityManager->merge($repository);
            $entityManager->flush();
            return $this->redirect($this->generateUrl('dokuwiki_translate_plugin', array('name' => $repository->getName())));
        } catch (NoResultException $ignored) {
            return $this->redirect($this->generateUrl('dokuwiki_translator_homepage'));
        }
    }

    private function activateRepository(RepositoryEntity $repository) {
        $repository->setState(RepositoryEntity::$STATE_ACTIVE);
        $repository->setActivationKey('');
    }

    public function showAction($name) {
        $data = array();
        $query = $this->getDoctrine()->getManager()->createQuery('
            SELECT repository, translations, lang
            FROM dokuwikiTranslatorBundle:RepositoryEntity repository
            JOIN repository.translations translations
            JOIN translations.language lang
            WHERE repository.type = :type
            AND repository.name = :name
        ');

        $query->setParameter('type', Repository::$TYPE_PLUGIN);
        $query->setParameter('name', $name);
        try {
            $data['repository'] = $query->getSingleResult();
        } catch (NoResultException $e) {
            return $this->redirect($this->generateUrl('dokuwiki_translator_homepage'));
        }

        return $this->render('dokuwikiTranslatorBundle:Default:show.html.twig', $data);
    }
}
