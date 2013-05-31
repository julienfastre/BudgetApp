<?php

namespace Fastre\BudgetBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;

class DefaultController extends Controller
{
    public function indexAction($name)
    {
        $client = $this->get('neo4jclient');
        
        print_r($client->getServerInfo());
        
        return $this->render('FastreBudgetBundle:Default:index.html.twig', array('name' => $name));
    }
}
