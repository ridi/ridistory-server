<?php
namespace Story\Controller\CpAdmin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\Book;
use Story\Model\CpAccount;
use Story\Model\DownloadSales;
use Symfony\Component\HttpFoundation\Request;
use Twig_SimpleFunction;

class CpAdminController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        /**
         * @var $cp_admin \Silex\ControllerCollection
         */
        $cp_admin = $app['controllers_factory'];

        $cp_admin->get(
            '/',
            function () use ($app) {
                return $app->redirect('/cp_admin/download_sales/list');
            }
        );

        $cp_admin->get('/download_sales/list', array($this, 'downloadSalesList'));

        return $cp_admin;
    }

    public static function downloadSalesList(Request $req, Application $app)
    {
        $cp_id = $req->get('cp_id');
        $cp = CpAccount::get($cp_id);

        $begin_date = $req->get('begin_date');
        $end_date = $req->get('end_date');
        $search_date = array(
            'begin_date' => $begin_date,
            'end_date' => $end_date
        );

        $download_sales = DownloadSales::getListByCpId($cp_id, null, null);

        $app['twig']->addFilter(
            new \Twig_SimpleFilter('simple_date_format', function($date) {
                return date('y.m.d', strtotime($date));
            })
        );

        $app['twig']->addFunction(
            new Twig_SimpleFunction('get_status', function ($begin_date, $end_date, $end_action_flag, $open_part_count = 0, $total_part_count = 0) {
                $today = strtotime('now');
                $is_ongoing = strtotime($begin_date) <= $today && strtotime($end_date) >= $today;
                if ($is_ongoing) {
                    $status = "연재중(" . $open_part_count . "/" . $total_part_count . ")";
                } else {
                    switch ($end_action_flag) {
                        case Book::ALL_FREE:
                            $status = "완결(모두 공개)";
                            break;
                        case Book::ALL_CHARGED:
                            $status = "완결(모두 잠금)";
                            break;
                        case Book::SALES_CLOSED:
                            $status = "판매종료(파트 비공개";
                            break;
                        case Book::ALL_CLOSED:
                            $status = "게시종료(전체 비공개)";
                            break;
                        default:
                            $status = "";
                    }
                }

                return $status;
            })
        );

        return $app['twig']->render(
            '/cp_admin/download_sales_list.twig',
            compact('cp', 'search_date', 'download_sales')
        );
    }
}