<?php

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminBuyerControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('list', array($this, 'buyerList'));
        $admin->get('{id}', array($this, 'buyerDetail'));

        return $admin;
    }

    public function buyerList(Request $req, Application $app)
    {
        $buyers = Buyer::getWholeList();
        return $app['twig']->render('admin/buyer_list.twig', array('buyers' => $buyers));
    }

    public function buyerDetail(Request $req, Application $app, $id)
    {
        $buyer = Buyer::get($id);

        $coin_in = Buyer::getCoinInList($id);
        $coin_out = Buyer::getCoinOutList($id);

        return $app['twig']->render('admin/buyer_detail.twig',
            array(
                'buyer' => $buyer,
                'coin_in' => $coin_in,
                'coin_out' => $coin_out
            ));
    }
}

?>