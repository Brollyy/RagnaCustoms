<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

class CmsController extends AbstractController
{
    /**
     * @return Response
     */
    #[Route(path: '/getting-started', name: 'getting_started')]
    public function gettingStarted(): Response
    {
        return $this->render('cms/getting_started.html.twig');
    }

    /**
     * @return Response
     */
    #[Route(path: '/ranking-system', name: 'ranking_system')]
    public function rankingSystem(): Response
    {
        return $this->render('cms/ranking_system.html.twig');
    }

    /**
     * @return Response
     */
    #[Route(path: '/acceptance-criteria', name: 'acceptance_criteria')]
    public function acceptanceCriteria(): Response
    {
        return $this->render('cms/acceptance_criteria.html.twig');
    }

    /**
     * @return Response
     */
    #[Route(path: '/', name: 'home')]
    public function homepage(): Response
    {

        return $this->render('cms/homepage.html.twig');
    }

}
