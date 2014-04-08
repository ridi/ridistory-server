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

        $cp_admin->get('/login', array($this, 'login'));
        $cp_admin->post('/login/check', array($this, 'checkLogin'));
        $cp_admin->get('/logout', array($this, 'logout'));

        $cp_admin->get('/my_info', array($this, 'myInfo'));
        $cp_admin->post('/my_info/edit', array($this, 'editMyPassword'));

        $cp_admin->get('/download_sales/list', array($this, 'downloadSalesList'));
        $cp_admin->get('/download_sales/{b_id}', array($this, 'downloadSalesDetail'));

        return $cp_admin;
    }

    public static function login(Request $req, Application $app)
    {
        if (CpAccount::checkCpLoginSession()) {
            return $app->redirect('/cp_admin');
        }

        return $app['twig']->render('/cp_admin/login.twig');
    }

    public static function checkLogin(Request $req, Application $app)
    {
        $cp_site_id = $req->get('id', false);
        $password = $req->get('pw');

        if (CpAccount::cpLogin($cp_site_id, $password)) {
            $app['session']->set('cp_user', $cp_site_id);
            return $app->redirect('/cp_admin');
        }

        $app['session']->getFlashBag()->add('alert', array('danger' => '아이디와 비밀번호를 확인해주세요.'));
        return $app->redirect('/cp_admin/login');
    }

    public static function logout(Request $req, Application $app)
    {
        $app['session']->clear();
        return $app->redirect('/cp_admin/login');
    }

    public static function myInfo(Request $req, Application $app)
    {
        if (!CpAccount::checkCpLoginSession()) {
            return $app->redirect('/cp_admin/login');
        }

        $cp_site_id = $app['session']->get('cp_user');
        $cp = CpAccount::getCpFromSiteId($cp_site_id);

        return $app['twig']->render('/cp_admin/my_info.twig', array('cp' => $cp));
    }

    public static function editMyPassword(Request $req, Application $app)
    {
        if (!CpAccount::checkCpLoginSession()) {
            return $app->redirect('/cp_admin/login');
        }

        $cp_site_id = $app['session']->get('cp_user');
        $cp = CpAccount::getCpFromSiteId($cp_site_id);

        $old_pw = $req->get('old_pw');
        $new_pw = $req->get('new_pw');
        $new_pw2 = $req->get('new_pw2');

        if ($new_pw != $new_pw2) {
            $app['session']->getFlashBag()->add('alert', array('danger' => '새로 입력하신 비밀번호가 서로 다릅니다.'));
        } else {
            if ($cp['password'] == $old_pw) {
                CpAccount::update($cp['id'], array('password' => $new_pw));
                $app['session']->getFlashBag()->add('alert', array('info' => '비밀번호가 수정되었습니다.'));
            } else {
                $app['session']->getFlashBag()->add('alert', array('danger' => '기존 비밀번호를 잘못 입력하셨습니다.'));
            }
        }

        return true;
    }

    public static function downloadSalesList(Request $req, Application $app)
    {
        if (!CpAccount::checkCpLoginSession()) {
            return $app->redirect('/cp_admin/login');
        }

        $cp_site_id = $app['session']->get('cp_user');
        $cp = CpAccount::getCpFromSiteId($cp_site_id);

        $begin_date = $req->get('begin_date');
        $end_date = $req->get('end_date');

        $total_sales = 0;
        $total_sales_royalty = 0;
        $total_charged_download = 0;

        $download_sales = DownloadSales::getListByCpId($cp['id'], $begin_date, $end_date);

        foreach ($download_sales as $ds) {
            // 헤더에 들어갈 정보 계산
            $total_sales += $ds['total_sales'];
            $total_sales_royalty += $ds['total_sales'] * $ds['royalty_percent'];
            $total_charged_download += $ds['charged_download'];
        }

        $app['twig']->addFilter(
            new \Twig_SimpleFilter('simple_date_format', function($date) {
                return date('y.m.d', strtotime($date));
            })
        );

        $app['twig']->addFunction(
            new Twig_SimpleFunction('get_status', function ($begin_date, $end_date, $end_action_flag, $open_part_count = 0, $total_part_count = 0) {
                $today = date('Y-m-d H:i:s');
                $today = strtotime($today);
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

        $bind = array(
            'cp' => $cp,
            'begin_date' => $begin_date,
            'end_date' => $end_date,
            'header' => array('total_sales' => $total_sales, 'total_sales_royalty' => $total_sales_royalty, 'total_download' => $total_charged_download),
            'download_sales' => $download_sales
        );

        return $app['twig']->render('/cp_admin/download_sales_list.twig', $bind);
    }

    public static function downloadSalesDetail(Request $req, Application $app, $b_id)
    {
        if (!CpAccount::checkCpLoginSession()) {
            return $app->redirect('/cp_admin/login');
        }

        $cp_site_id = $app['session']->get('cp_user');
        $cp = CpAccount::getCpFromSiteId($cp_site_id);

        $begin_date = $req->get('begin_date');
        $end_date = $req->get('end_date');

        $total_sales = 0;
        $total_charged_download = 0;

        $download_sales = DownloadSales::get($b_id, $begin_date, $end_date);
        $download_sales_detail = DownloadSales::getPartSalesList($b_id, $begin_date, $end_date);
        foreach ($download_sales_detail as $dsd) {
            // 푸터에 들어갈 정보 계산
            $total_sales += $dsd['total_coin_amount'];
            $total_charged_download += $dsd['charged_download'];
        }

        $app['twig']->addFunction(
            new Twig_SimpleFunction('get_date_scope', function ($begin_date, $end_date) {
                if (!$begin_date && !$end_date) {
                    $scope = '전체';
                } else if ($begin_date && !$end_date) {
                    $scope = $begin_date . ' ~ 현재';
                } else if (!$$begin_date && $end_date) {
                    $scope = '연재시작 ~ ' . $end_date;
                } else {
                    $scope = $begin_date . ' ~ ' . $end_date;
                }
                return $scope;
            })
        );

        $bind = array(
            'cp' => $cp,
            'begin_date' => $begin_date,
            'end_date' => $end_date,
            'download_sales' => $download_sales,
            'download_sales_detail' => $download_sales_detail,
            'footer' => array('total_sales' => $total_sales, 'total_download' => $total_charged_download)
        );

        return $app['twig']->render('cp_admin/download_sales_detail.twig', $bind);
    }
}