<?php

namespace App\Controller;

use App\Entity\Series;
use Doctrine\ORM\PersistentCollection;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

class SeasonsController extends AbstractController
{
    public function __construct(private CacheInterface $cache)
    {}

    #[Route('/series/{series}/seasons', name: 'app_seasons')]
    public function index(Series $series): Response
    {
        $seasons = $this->cache->get(
            // String que representa a chave do que vai ser buscado.
            "series_{$series->getId()}_seasons", 
            function (ItemInterface $item) use ($series) { 
                // Função caso não ache a chave no cache.
                // O comando use passa o parâmetro $series para o bloco.

                $item->expiresAfter(new \DateInterval(duration: 'PT10S'));
                // A barra em \DateInterval é para não precisar de 
                // importar usando o comando use. PT10S significa 10 segundos.
                
                /** @var PersistentCollection $seasons 
                 * Sem a anotação acima, o método initialize fica inacessível.
                */
                $seasons = $series->getSeasons();
                // Garantir que a coleção é inicializada antes de guardar no cache.
                $seasons->initialize(); 

                return $seasons;
            }
        );

        return $this->render('seasons/index.html.twig', [
            'seasons' => $seasons,
            'series' => $series,
        ]);
    }
}
