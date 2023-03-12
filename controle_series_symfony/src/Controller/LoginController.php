<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;

class LoginController extends AbstractController
{
    #[Route('/login', name: 'app_login')]
    public function index(AuthenticationUtils $autehenticationUtils): Response
    {
        // Última mensagem de erro de autenticação.
        $error = $autehenticationUtils->getLastAuthenticationError();
        // Último usuário que tentou ser autenticado.
        $lastUsername = $autehenticationUtils->getLastUsername();

        return $this->render('login/index.html.twig', [
            'error' => $error,
            'last_username' => $lastUsername,
    ]);    }
}
