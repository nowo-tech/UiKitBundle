<?php

declare(strict_types=1);

namespace App\Controller;

use Nowo\UiKitBundle\Form\ImageCropTypeExtension;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * UiKit 2.43 demo: the image crop step on file inputs (FileType option `nowo_ui_image_crop`, js/nowo-ui-image-crop.js). After a
 * submit the page lists what the server received, read from the bytes (getimagesize), as a host would validate it. The response
 * forbids inline style attributes, inline scripts and inline handlers (CSP), which the e2e checks. Fixtures only; nothing is stored.
 */
final class KitImageCropController extends AbstractController
{
    #[Route('/kit/image-crop', name: 'kit_image_crop', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $form = $this->createFormBuilder()
            ->add('avatar', FileType::class, ['label' => 'Avatar (circle, 256 px, WebP)', 'required' => false, 'attr' => ['accept' => 'image/png,image/jpeg,image/webp'],
                ImageCropTypeExtension::OPTION => ['shape' => 'circle', 'max' => 256, 'format' => 'webp', 'preview' => 'avatar-preview']])
            ->add('logo', FileType::class, ['label' => 'Logo (16:9, 800 px, PNG)', 'required' => false, 'attr' => ['accept' => 'image/*'],
                ImageCropTypeExtension::OPTION => ['aspect' => '16/9', 'max' => 800]])
            ->add('photo', FileType::class, ['label' => 'Photo (free aspect, JPEG)', 'required' => false,
                ImageCropTypeExtension::OPTION => ['aspect' => 'free', 'max' => 4096, 'format' => 'jpeg', 'quality' => 0.9]])
            ->add('badge', FileType::class, ['label' => 'Badge (transparent circle, 128 px)', 'required' => false,
                ImageCropTypeExtension::OPTION => ['shape' => 'circle', 'bake_circle' => true, 'max' => 128]])
            ->add('document', FileType::class, ['label' => 'Document (no crop)', 'required' => false])
            ->add('save', SubmitType::class, ['label' => 'Upload'])
            ->getForm();
        $form->handleRequest($request);

        $received = [];
        if ($form->isSubmitted() && $form->isValid()) {
            foreach (['avatar', 'logo', 'photo', 'badge', 'document'] as $name) {
                $file = $form->get($name)->getData();
                if ($file instanceof UploadedFile && $file->isValid()) {
                    $size = @getimagesize($file->getPathname());
                    $received[$name] = [
                        'name' => $file->getClientOriginalName(),
                        'type' => false === $size ? 'not an image' : $size['mime'],
                        'size' => false === $size ? '' : $size[0].'x'.$size[1],
                    ];
                }
            }
        }

        $response = $this->render('kit/image_crop.html.twig', ['form' => $form->createView(), 'received' => $received]);
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'; img-src 'self' blob:");

        return $response;
    }
}
