<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\Type\ArticleType;
use App\Form\Type\SearchType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\String\Slugger\SluggerInterface;

#[Route('/articles', name: 'articles_')]
class ArticleController extends AbstractController
{
    #[Route('', name: 'index')]
    public function index(
        EntityManagerInterface $em,
    ): Response
    {
        /** @var Article[] $articles */
        $articles = $em->getRepository(Article::class)->findByUser($this->getUser());

        return $this->render('article/index.html.twig', [
            'articles' => $articles,
        ]);
    }

    #[Route('/create', name: 'create')]
    #[Route('/{id}/edit', name: 'edit')]
    public function createOrUpdate(
        Request $request,
        EntityManagerInterface $em,
        SluggerInterface $slugger,
        #[Autowire('%kernel.project_dir%/public/uploads/images')] string $imagesDirectory,
        ?Article $article
    ): Response
    {
        if (empty($article)) {
            $article = new Article();
        } else {
            $this->denyAccessUnlessGranted('edit', $article);
        }
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article = $form->getData();
            $article->setUser($this->getUser());

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

            return $this->redirectToRoute("articles_index");
        }

        return $this->render('article/form.html.twig', [
            'article' => $article,
            "form" => $form,
        ]);
    }

    #[Route('/{id}/detail', name: 'detail')]
    public function detail(
        Article $article
    ): Response {
        $this->denyAccessUnlessGranted('view', $article);

        return $this->render('article/detail.html.twig', [
            "article" => $article,
        ]);
    }
}
