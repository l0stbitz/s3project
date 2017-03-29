<?php

namespace AppBundle\Controller;

use Sensio\Bundle\FrameworkExtraBundle\Configuration\Route;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

class DefaultController extends Controller
{
    /**
     * @Route("/", name="homepage")
     */
    public function indexAction(Request $request)
    {
        // replace this example code with whatever you need
        return $this->render('AppBundle:default:index.html.twig');
    }
    
    /**
     * @Route("/search", name="search")
     */
    public function searchAction(Request $request)
    {
        $em = $this->getDoctrine()->getManager();

        $start     = $request->get('start', 0);
        $limit     = $request->get('length', 10);
        $search    = $request->get('search', []);
        $order     = $request->get('order', []);
        $columns   = $request->get('columns', []);

        $s3Service = $this->container->get('app.media_service');


        $res = $s3Service->searchBucket($start, $limit, $search, $order, $columns);
        $this->view['data']            = $res['data'];
        $this->view['draw']            = $request->get('draw', 1);
        $this->view['recordsTotal']    = $res['recordsTotal'];
        $this->view['recordsFiltered'] = $res['recordsFiltered'];
 
        $response = new JsonResponse($this->view);
        $response->setPublic();
        // cache for 15 minutes
        $response->setMaxAge(60 * 15);

        return $response;
    }
}
