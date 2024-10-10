<?php

namespace App\DataFixtures;

use App\Entity\Advice;
use App\Entity\Month;
use App\Entity\User;
use Doctrine\Bundle\FixturesBundle\Fixture;
use Doctrine\Persistence\ObjectManager;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AppFixtures extends Fixture
{
    private $userPasswordHasher;

    public function __construct(UserPasswordHasherInterface $userPasswordHasher)
    {
        $this->userPasswordHasher = $userPasswordHasher;
    }

    public function load(ObjectManager $manager): void
    {
        // Création d'un user "normal"
        $user = new User();
        $user->setEmail("user@ecogardenapi.com");
        $user->setRoles(["ROLE_USER"]);
        $user->setPassword($this->userPasswordHasher->hashPassword($user, "password"));
        $user->setTown("Paris");
        $manager->persist($user);

        // Création d'un user admin
        $userAdmin = new User();
        $userAdmin->setEmail("admin@ecogardenapi.com");
        $userAdmin->setRoles(["ROLE_ADMIN"]);
        $userAdmin->setPassword($this->userPasswordHasher->hashPassword($userAdmin, "password"));
        $userAdmin->setTown("Bordeaux");
        $manager->persist($userAdmin);

        // Création des mois
        $monthList = [];

        for ($i = 1; $i < 13; $i++) {
            $month = new Month();
            $month->setMonthNumber($i);
            $manager->persist($month);
            $monthList[] = $month;
        }

        // Création des conseils
        $advice1 = new Advice();
        $advice1->setDescription('Plantez des herbes aromatiques comme le basilic et la menthe au printemps.');
        $advice1->addMonth($monthList[3]);
        $advice1->addMonth($monthList[4]);
        $manager->persist($advice1);

        $advice2 = new Advice();
        $advice2->setDescription('En mai, n’oubliez pas d’arroser régulièrement vos jeunes plants.');
        $advice2->addMonth($monthList[5]);
        $manager->persist($advice2);

        $advice3 = new Advice();
        $advice3->setDescription('En été, protégez vos légumes du soleil direct avec des filets d’ombre.');
        $advice3->addMonth($monthList[6]);
        $advice3->addMonth($monthList[7]);
        $manager->persist($advice3);

        $advice4 = new Advice();
        $advice4->setDescription('À l’automne, récoltez vos légumes avant les premières gelées.');
        $advice4->addMonth($monthList[9]);
        $advice4->addMonth($monthList[10]);
        $manager->persist($advice4);

        $advice5 = new Advice();
        $advice5->setDescription('Préparez votre jardin pour l’hiver en protégeant vos plantes sensibles.');
        $advice5->addMonth($monthList[11]);
        $manager->persist($advice5);

        $advice6 = new Advice();
        $advice6->setDescription('En mars, commencez vos semis en intérieur pour les plantes sensibles au froid.');
        $advice6->addMonth($monthList[3]);
        $manager->persist($advice6);

        $advice7 = new Advice();
        $advice7->setDescription('Ne négligez pas le compostage ! Ajoutez des déchets organiques toute l’année.');
        $advice7->addMonth($monthList[0]);
        $advice7->addMonth($monthList[11]);
        $manager->persist($advice7);

        $advice8 = new Advice();
        $advice8->setDescription('En octobre, plantez des bulbes de fleurs pour avoir un joli jardin au printemps.');
        $advice8->addMonth($monthList[10]);
        $manager->persist($advice8);

        $manager->flush();
    }
}