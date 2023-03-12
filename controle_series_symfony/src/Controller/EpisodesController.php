<?php

namespace App\Controller;

use App\Entity\Season;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class EpisodesController extends AbstractController
{
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
        dd(array_keys($request->request->all('episodes'))); 

        return $this->redirectToRoute('app_episodes');
    }
}
