<?php

namespace App\Entity;

use App\Repository\MonthRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Serializer\Annotation\Groups;

#[ORM\Entity(repositoryClass: MonthRepository::class)]
class Month
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    #[Groups(["getAdvice"])]
    private ?int $id = null;

    #[ORM\Column]
    #[Groups(["getAdvice"])]
    private ?int $monthNumber = null;

    /**
     * @var Collection<int, Advice>
     */
    #[ORM\ManyToMany(targetEntity: Advice::class, inversedBy: 'months')]
    private Collection $adviceList;

    public function __construct()
    {
        $this->adviceList = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMonthNumber(): ?int
    {
        return $this->monthNumber;
    }

    public function setMonthNumber(int $monthNumber): static
    {
        $this->monthNumber = $monthNumber;

        return $this;
    }

    /**
     * @return Collection<int, Advice>
     */
    public function getAdviceList(): Collection
    {
        return $this->adviceList;
    }

    public function addAdvice(Advice $advice): static
    {
        if (!$this->adviceList->contains($advice)) {
            $this->adviceList->add($advice);
        }

        return $this;
    }

    public function removeAdvice(Advice $advice): static
    {
        $this->adviceList->removeElement($advice);

        return $this;
    }
}
