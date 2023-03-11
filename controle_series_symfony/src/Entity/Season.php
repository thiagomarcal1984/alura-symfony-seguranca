<?php

namespace App\Entity;

use App\Repository\SeasonRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: SeasonRepository::class)]
class Season
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: Types::INTEGER)]
    private int $id;

    #[ORM\OneToMany(
        mappedBy: 'season', 
        targetEntity: Episode::class, 
        orphanRemoval: true,
        // Persistência em cascata de episódios.
        cascade: ['persist']
    )]
    private Collection $episodes;

    #[ORM\ManyToOne(inversedBy: 'seasons')]
    // Repare a referencia ao atributo 'seasons' da entidade 'Series'.
    #[ORM\JoinColumn(nullable: false)]
    private Series $series;

    public function __construct(
        #[ORM\Column(type: Types::SMALLINT)]
        private int $number    
    )
    {
        $this->episodes = new ArrayCollection();
    }

    public function getId(): int
    {
        return $this->id;
    }

    public function getNumber(): int
    {
        return $this->number;
    }

    public function setNumber(int $number): self
    {
        $this->number = $number;

        return $this;
    }

    /**
     * @return Collection<int, Episode>
     */
    public function getEpisodes(): Collection
    {
        return $this->episodes;
    }

    public function addEpisode(Episode $episode): self
    {
        if (!$this->episodes->contains($episode)) {
            $this->episodes->add($episode);
            $episode->setSeason($this);
        }

        return $this;
    }

    public function removeEpisode(Episode $episode): self
    {
        if ($this->episodes->removeElement($episode)) {
            // set the owning side to null (unless already changed)
            if ($episode->getSeason() === $this) {
                $episode->setSeason(null);
            }
        }

        return $this;
    }

    public function getSeries(): Series
    {
        return $this->series;
    }

    public function setSeries(Series $series): self
    {
        $this->series = $series;

        return $this;
    }
}
