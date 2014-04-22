<?php
namespace Story\Controller\Admin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Story\Model\CoinProduct;
use Twig_SimpleFunction;

class CoinProductController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('add', array($this, 'addCoinProduct'));
        $admin->get('list', array($this, 'coinProductList'));
        $admin->get('{coin_id}', array($this, 'coinProductDetail'));
        $admin->post('{coin_id}/delete', array($this, 'deleteCoinProduct'));
        $admin->post('{coin_id}/edit', array($this, 'editCoinProduct'));

        return $admin;
    }

    public static function addCoinProduct(Application $app)
    {
        $coin_id = CoinProduct::createCoinProduct();

        $app['session']->getFlashBag()->add('alert', array('success' => '코인 상품이 추가되었습니다.'));
        return $app->redirect('/admin/coin_product/' . $coin_id);
    }

    public static function coinProductList(Application $app)
    {
        $coin_list = CoinProduct::getCoinProductsByType(CoinProduct::TYPE_ALL);

        $app['twig']->addFunction(
            new Twig_SimpleFunction('get_type', function ($type) {
                switch ($type) {
                    case CoinProduct::TYPE_INAPP:
                        return '구글 인앱';
                    case CoinProduct::TYPE_RIDICASH:
                        return '리디캐쉬';
                    default:
                        return '';
                }
            })
        );

        return $app['twig']->render('/admin/coin_product_list.twig', array('coin_list' => $coin_list));
    }

    public static function coinProductDetail(Request $req, Application $app, $coin_id)
    {
        $coin_product = CoinProduct::getCoinProduct($coin_id);
        return $app['twig']->render('/admin/coin_product_detail.twig', array('coin' => $coin_product));
    }

    public static function deleteCoinProduct(Request $req, Application $app, $coin_id)
    {
        $r = CoinProduct::deleteCoinProduct($coin_id);
        if ($r) {
            $app['session']->getFlashBag()->add('alert', array('info' => '코인 상품이 삭제되었습니다.'));
        } else {
            $app['session']->getFlashBag()->add('alert', array('error' => '코인 상품을 삭제하는 도중 오류가 발생하였습니다. (DB 오류)'));
        }
        return $app->json(array('success' => true));
    }

    public static function editCoinProduct(Request $req, Application $app, $coin_id)
    {
        $inputs = $req->request->all();

        $r = CoinProduct::updateCoinProduct($coin_id, $inputs);
        if ($r) {
            $app['session']->getFlashBag()->add('alert', array('info' => '코인 상품 정보가 수정되었습니다.'));
        } else {
            $app['session']->getFlashBag()->add('alert', array('error' => '코인 상품 정보를 수정하는 도중 오류가 발생하였습니다. (DB 오류)'));
        }
        return $app->redirect('/admin/coin_product/' . $coin_id);
    }
}