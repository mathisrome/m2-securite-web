<?php

namespace App\Command;

use App\Repository\ArticleRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'app:article:prune',
    description: 'Add a short description for your command',
)]
class ArticlePruneCommand extends Command
{
    public function __construct(
        private ArticleRepository $articleRepository,
        private EntityManagerInterface $entityManager,
    )
    {
        parent::__construct();
    }

    protected function configure(): void
    {

    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $articles = $this->articleRepository->findBy(['isArchived' => true]);

        if (empty($articles)) {
            $io->success('<info>No articles found</info>');
            return Command::SUCCESS;
        }

        foreach ($articles as $article) {
            $this->entityManager->remove($article);
        }
        $this->entityManager->flush();

        $io->success('Removed ' . count($articles) . ' articles!');

        return Command::SUCCESS;
    }
}
