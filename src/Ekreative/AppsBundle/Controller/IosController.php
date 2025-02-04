<?php

namespace Ekreative\AppsBundle\Controller;

use Ekreative\AppsBundle\Entity\IosApp;
use Ekreative\AppsBundle\Entity\IosFolder;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;

class IosController extends BaseController
{

    public function indexAction(Request $request, IosFolder $folder)
    {

        $app = new IosApp();
        $app->setFolder($folder);
        $form = $this->newAppForm($app);

        $folderType = $this->getCurrentFolderType();

        return $this->render('EkreativeAppsBundle:Ios:appsList.html.twig', array(
                'folder'            => $folder,
                'appform'           => $form->createView(),
                'currentFolderType' => BaseController::FOLDER_ANDROID,
                'serveLink'         => $this->serveLink($folderType)
            )
        );
    }

    public function newAction(Request $request, IosFolder $folder)
    {

        $em = $this->getDoctrine()->getManager();

        $app = new IosApp();
        $app->setFolder($folder);
        $app->setDate(new \DateTime());

        $s3 = $this->container->getParameter('amazon_s3_base_url');

        $form = $this->newAppForm($app);

        $form->handleRequest($request);
        $em->persist($app);
        $em->flush();

        $uploader = $this->getFileUploader();

        $headers = array(
            'ContentType'        => 'application/octet-stream',
            'ContentDisposition' => 'attachment;filename="' . $app->getFilename() . '"'
        );

        $url = $s3 . '/' . $uploader->upload($app->getUploadedFile(), $app->getS3name(), $headers);

        $plist = \Ekreative\AppsBundle\Helper\Helper::getPlistString($url, $app->getBundleIdentifier(), $app->getVersion(), $app->getFilename());

        $uploader->uploadString($plist, $app->getS3Plistname(), ['contentType' => 'application/x-plist']);


        $installUrl = $this->generateUrl('ekreative_ios_app_install', array('token' => $app->getToken()), true);

        $qrcode = 'http://chart.apis.google.com/chart?chl=' . urlencode($installUrl) . '&chs=200x200&choe=UTF-8&cht=qr&chld=L%7C2';
        $app->setAlternativeComment($app->getUploadedFile()->getClientOriginalName());
        $app->setQrcode($qrcode);
        $em->persist($app);
        $em->flush();

        return new RedirectResponse($this->generateUrl('ekreative_folder_ios_index', array('id' => $folder->getId())));
    }

    public function installAction($token)
    {

        $s3  = $this->container->getParameter('amazon_s3_base_url');
        $app = $this->getDoctrine()->getRepository('EkreativeAppsBundle:IosApp')->findOneBy(['token' => $token]);
        if ($app) {

            $installUrl = $this->generateUrl('ekreative_ios_app_install', array('token' => $app->getToken()), true);
            $qrcode = 'http://chart.apis.google.com/chart?chl=' . urlencode($installUrl) . '&chs=200x200&choe=UTF-8&cht=qr&chld=L%7C2';
            $app->setQrcode($qrcode);
            return $this->render('EkreativeAppsBundle:Ios:install.html.twig', array(
                    'S3Plistname' => $s3 . '/' . $app->getS3Plistname(),
                    'app'         => $app
                )
            );
        }
    }

    public function deleleAction(Request $request, IosApp $app)
    {

        $folderId = $app->getFolder()->getId();
        $em       = $this->getDoctrine()->getManager();

        $uploader = $this->getFileUploader();
        $uploader->delete($app->getS3name());

        $em->remove($app);
        $em->flush();

        return new RedirectResponse($this->generateUrl('ekreative_folder_ios_index', array('id' => $folderId)));
    }

    private function newAppForm($entity)
    {
        return $this->createFormBuilder($entity)
                    ->add('uploadedFile', 'file', array('attr' => array('placeholder' => 'version', 'class' => "form-control")))
                    ->add('version', 'text', array('required' => false, 'attr' => array('placeholder' => 'version', 'class' => "form-control")))
                    ->add('bundleIdentifier', 'text', array('required' => true, 'attr' => array('placeholder' => 'bundleIdentifier', 'class' => "form-control")))
                    ->add('comment', 'text', array('required' => false, 'attr' => array('placeholder' => 'comment', 'class' => "form-control")))
                    ->setAction($this->generateUrl('ekreative_ios_app_new', array('folder' => $entity->getFolder()->getId())))
                    ->setMethod('POST')
                    ->add('save', 'submit', ['label' => 'Upload'])
                    ->getForm();
    }





}
