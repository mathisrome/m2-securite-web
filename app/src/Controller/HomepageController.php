<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\Type\ArticleType;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomepageController extends AbstractController
{
    #[Route('', name: 'homepage')]
    public function index(
        Request $request,
        EntityManagerInterface $em,
    ): Response
    {
        $article = $em->getRepository(Article::class)->findOneBy([]);
        $form = $this->createForm(ArticleType::class, $article);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $article = $form->getData();
            $em->persist($article);
            $em->flush();
        }

        return $this->render('homepage/index.html.twig', [
            'article' => $article,
            "form" => $form,
        ]);
    }
}
