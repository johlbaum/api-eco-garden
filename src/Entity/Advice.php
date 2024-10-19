<?php

namespace App\Entity;

use App\Repository\AdviceRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdviceRepository::class)]
class Advice
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getAdvice"])]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT)]
    #[Groups(["getAdvice"])]
    #[Assert\NotBlank(message: "La description du conseil est obligatoire.")]
    private ?string $description = null;

    /**
     * @var Collection<int, Month>
     */
    #[ORM\ManyToMany(targetEntity: Month::class, mappedBy: 'adviceList')]
    #[Groups(["getAdvice"])]
    #[Assert\Count(min: 1, minMessage: "Au moins un mois doit être associé au conseil.")]
    private Collection $months;

    public function __construct()
    {
        $this->months = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(string $description): static
    {
        $this->description = $description;

        return $this;
    }

    /**
     * @return Collection<int, Month>
     */
    public function getMonths(): Collection
    {
        return $this->months;
    }

    public function addMonth(Month $month): static
    {
        if (!$this->months->contains($month)) {
            $this->months->add($month);
            $month->addAdvice($this);
        }

        return $this;
    }

    public function removeMonth(Month $month): static
    {
        if ($this->months->removeElement($month)) {
            $month->removeAdvice($this);
        }

        return $this;
    }

    public function clearMonths(): static
    {
        foreach ($this->months as $month) {
            $this->removeMonth($month);
        }

        return $this;
    }
}
