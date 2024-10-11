<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Serializer\Normalizer\AbstractNormalizer;
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

    /**
     * Permet de mettre à jour un compte utilisateur.
     */
    #[Route('/api/user/{id}', name: 'app_updateUser', methods: ['PUT'])]
    public function updateUser(
        int $id,
        UserRepository $userRepository,
        Request $request,
        SerializerInterface $serializer,
        ValidatorInterface $validator,
        EntityManagerInterface $entityManager
    ): JsonResponse {
        // On récupère l'utilisateur à mettre à jour en base de données.
        $user = $userRepository->find($id);

        // On vérifie si l'utilisateur existe.
        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // On récupère le contenu de la requête.
        $jsonUser = $request->getContent();

        // On désérialise le contenu JSON en un objet User.
        $updatedUser = $serializer->deserialize(
            $jsonUser,
            User::class,
            'json',
            [AbstractNormalizer::OBJECT_TO_POPULATE => $user]
        );

        // On valide l'objet User et on retourne les erreurs s'il y en a.
        $validationErrors = $validator->validate($updatedUser);
        if ($validationErrors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($validationErrors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        // On enregistre l'utilisateur en base de données.
        $entityManager->persist($updatedUser);
        $entityManager->flush();

        // On sérialise l'objet utilisateur mis à jour pour le retourner dans la réponse.
        $updatedUserJson = $serializer->serialize($updatedUser, 'json');

        return new JsonResponse($updatedUserJson, JsonResponse::HTTP_OK, [], true);
    }

    /**
     * Permet de supprimer utilisateur.
     */
    #[Route('/api/user/{id}', name: 'app_deleteUser', methods: ['DELETE'])]
    public function deleteUser(int $id, UserRepository $userRepository, EntityManagerInterface $entityManager): JsonResponse
    {
        // On récupère l'utilisateur à supprimer en base de données.
        $user = $userRepository->find($id);

        // On vérifie si l'utilisateur existe.
        if (!$user) {
            return new JsonResponse(['message' => 'User not found'], JsonResponse::HTTP_NOT_FOUND);
        }

        // On supprime l'utilisateur en base de données.
        $entityManager->remove($user);
        $entityManager->flush();

        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
