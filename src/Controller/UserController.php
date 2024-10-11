<?php

namespace App\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\SerializerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class UserController extends AbstractController
{
    /**
     * Permet de créer un nouveau compte utilisateur.
     */
    #[Route('/api/user', name: 'app_createUser', methods: ['POST'])]
    public function createUser(
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        UserPasswordHasherInterface $userPasswordHasher,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // On récupère le contenu de la requête.
        $requestContent = $request->getContent();

        // On désérialise le contenu JSON en un objet User.
        $newUser = $serializer->deserialize($requestContent, User::class, 'json');

        // On valide l'objet User et on retourne les erreurs s'il y en a.
        $validationErrors = $validator->validate($newUser);
        if ($validationErrors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($validationErrors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        // On hash le mot de passe de l'utilisateur.
        $hashedPassword = $userPasswordHasher->hashPassword($newUser, $newUser->getPassword());
        $newUser->setPassword($hashedPassword);

        // On enregristre le nouvel utilisateur dans la base de données.
        $entityManager->persist($newUser);
        $entityManager->flush();

        // On sérialise l'objet utilisateur créé pour le retourner dans la réponse.
        $createdUserJson = $serializer->serialize($newUser, 'json');

        return new JsonResponse($createdUserJson, Response::HTTP_CREATED, [], true);
    }
}
