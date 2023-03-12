<?php

namespace App\Controller;

use App\Entity\Season;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EpisodesController extends AbstractController
{
    public function __construct(
        private EntityManagerInterface $entityManager
    ) {}

    #[Route('/season/{season}/episodes', name: 'app_episodes', methods: ['GET'])]
    public function index(Season $season): Response
    {
        return $this->render('episodes/index.html.twig', [
            'season' => $season,
            'series' => $season->getSeries(),
            'episodes' => $season->getEpisodes(),
        ]);
    }
    #[Route('/season/{season}/episodes', name: 'app_watch_episodes', methods: ['POST'])]
    public function watch(Season $season, Request $request): Response
    {
        // Retornaria um dado escalar: vai quebrar.
        // dd($request->request->get('episodes')); 
        
        // Retorna um array, um dado não escalar.
        // dd($request->request->all('episodes')); 
        
        // Queremos só os IDs, não o status de cada episódio.
        $watchedEpisodes = array_keys($request->request->all('episodes'));
        $episodes = $season->getEpisodes();

        foreach ($episodes as $episode) {
            // Se o ID estiver na lista de assistidos, marca como true.
            $episode->setWatched(in_array($episode->getId(), $watchedEpisodes));
        }

        $this->entityManager->flush();

        $this->addFlash("success", "Episódios marcados como assistidos.");

        return $this->redirectToRoute('app_episodes', ['season' => $season->getId()]);
        // return new RedirectResponse("/season/{$season->getId()}/episodes");
    }
}
