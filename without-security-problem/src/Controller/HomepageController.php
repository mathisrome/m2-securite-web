<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\Type\ArticleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\String\Slugger\SluggerInterface;

class HomepageController extends AbstractController
{
    #[Route('', name: 'homepage')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads/images')] string $imagesDirectory
    ): Response
    {
        /** @var Article|null $article */
        $article = $em->getRepository(Article::class)->findOneBy([]);
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article = $form->getData();

            /** @var UploadedFile $imageFile */
            $imageFile = $form->get('image')->getData();

            // this condition is needed because the 'brochure' field is not required
            // so the PDF file must be processed only when a file is uploaded
            if ($imageFile) {
                $originalFilename = pathinfo($imageFile->getClientOriginalName(), PATHINFO_FILENAME);
                // this is needed to safely include the file name as part of the URL
                $safeFilename = $slugger->slug($originalFilename);
                $newFilename = $safeFilename.'-'.uniqid().'.'.$imageFile->guessExtension();

                // Move the file to the directory where brochures are stored
                try {
                    $imageFile->move($imagesDirectory, $newFilename);
                } catch (FileException $e) {
                    // ... handle exception if something happens during file upload
                }

                // updates the 'brochureFilename' property to store the PDF file name
                // instead of its contents
                $article->setImageFilename($newFilename);
                $article->setOriginalImageFilename($originalFilename);
            }

            $em->persist($article);
            $em->flush();
        }

        return $this->render('homepage/index.html.twig', [
            'article' => $article,
            "form" => $form,
        ]);
    }
}
