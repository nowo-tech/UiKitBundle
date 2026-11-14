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
            ->add('avatar', FileType::class, ['label' => 'Avatar (circle, 256 px, auto format, 200 KB)', 'required' => false, 'attr' => ['accept' => 'image/png,image/jpeg,image/webp'],
                ImageCropTypeExtension::OPTION => ['shape' => 'circle', 'max' => 256, 'max_bytes' => 200_000, 'preview' => 'avatar-preview']])
            ->add('logo', FileType::class, ['label' => 'Logo (16:9, 800 px, PNG)', 'required' => false, 'attr' => ['accept' => 'image/*'],
                ImageCropTypeExtension::OPTION => ['aspect' => '16/9', 'max' => 800, 'format' => 'png']])
            ->add('photo', FileType::class, ['label' => 'Photo (free aspect, JPEG)', 'required' => false,
                ImageCropTypeExtension::OPTION => ['aspect' => 'free', 'max' => 4096, 'format' => 'jpeg', 'quality' => 0.9]])
            ->add('badge', FileType::class, ['label' => 'Badge (transparent circle, 128 px)', 'required' => false,
                ImageCropTypeExtension::OPTION => ['shape' => 'circle', 'bake_circle' => true, 'max' => 128]])
            ->add('large', FileType::class, ['label' => 'Large photo (free aspect, 1600 px, auto format, 300 000 bytes)', 'required' => false,
                ImageCropTypeExtension::OPTION => ['aspect' => 'free', 'max' => 1600, 'max_bytes' => 300_000]])
            ->add('brand', FileType::class, ['label' => 'Logo with transparency (free aspect, auto format)', 'required' => false,
                ImageCropTypeExtension::OPTION => ['aspect' => 'free', 'max' => 800]])
            ->add('tiny', FileType::class, ['label' => 'Impossible limit (100 bytes)', 'required' => false,
                ImageCropTypeExtension::OPTION => ['max_bytes' => 100]])
            ->add('document', FileType::class, ['label' => 'Document (no crop)', 'required' => false])
            ->add('save', SubmitType::class, ['label' => 'Upload'])
            ->getForm();
        $form->handleRequest($request);
        // Upload on pick: the kit's autosubmit posts this form when the input changes (after Apply; never after Cancel).
        $quick = $this->container->get('form.factory')->createNamedBuilder('quick')
            ->add('picture', FileType::class, ['label' => 'Profile picture, sent on pick (autosubmit)', 'required' => false, 'attr' => ['data-nowo-ui-autosubmit' => ''],
                ImageCropTypeExtension::OPTION => ['shape' => 'circle', 'max' => 128, 'format' => 'png']])
            ->getForm();
        $quick->handleRequest($request);

        $received = [];
        if ($form->isSubmitted() && $form->isValid()) {
            foreach (['avatar', 'logo', 'photo', 'badge', 'large', 'brand', 'tiny', 'document'] as $name) {
                $this->describe($form->get($name)->getData(), $name, $received);
            }
        }
        if ($quick->isSubmitted() && $quick->isValid()) {
            $received['quick'] = ['name' => '(none)', 'type' => '', 'size' => '', 'bytes' => 0];
            $this->describe($quick->get('picture')->getData(), 'quick', $received);
        }

        $response = $this->render('kit/image_crop.html.twig', ['form' => $form->createView(), 'quick' => $quick->createView(), 'received' => $received]);
        $response->headers->set('Content-Security-Policy', "script-src 'self'; style-src-attr 'none'; script-src-attr 'none'; img-src 'self' blob:");

        return $response;
    }

    /**
     * What the server received for one field, read from the bytes.
     *
     * @param array<string, array{name: string, type: string, size: string, bytes: int}> $received
     */
    private function describe(mixed $file, string $name, array &$received): void
    {
        if ($file instanceof UploadedFile && $file->isValid()) {
            $size = @getimagesize($file->getPathname());
            $received[$name] = [
                'name' => $file->getClientOriginalName(),
                'type' => false === $size ? 'not an image' : $size['mime'],
                'size' => false === $size ? '' : $size[0].'x'.$size[1],
                'bytes' => (int) $file->getSize(),
            ];
        }
    }
}
