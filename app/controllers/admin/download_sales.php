<?php

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Model\Book;
use Story\Model\DownloadSales;
use Symfony\Component\HttpFoundation\Request;

class AdminDownloadSalesControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('list', array($this, 'downloadSalesList'));
        $admin->get('{b_id}', array($this, 'downloadSalesDetail'));

        return $admin;
    }

    public function downloadSalesList(Request $req, Application $app)
    {
        $search_begin_date = $req->get('begin_date', '0000-00-00');
        $search_end_date = $req->get('end_date', date('Y-m-d'));
        if ($search_begin_date == '') {
            $search_begin_date = '0000-00-00';
        }
        if ($search_end_date == '') {
            $search_end_date = date('Y-m-d');
        }
        $search_date = array(
            'begin_date' => $search_begin_date,
            'end_date' => $search_end_date
        );

        $total_sales = 0;
        $total_sales_royalty = 0;
        $total_free_download = 0;
        $total_charged_download = 0;

        $download_sales = DownloadSales::getWholeList($search_begin_date, $search_end_date);

        foreach ($download_sales as $ds) {
            // 헤더에 들어갈 정보 계산
            $total_sales += $ds['total_sales'];
            $total_sales_royalty += $ds['total_sales'] * $ds['royalty_percent'];
            $total_free_download += $ds['free_download'];
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
                $is_ongoing = strtotime($begin_date) <= strtotime($today) && strtotime($end_date) >= strtotime($today);
                if ($is_ongoing) {
                    $status = "연재중(" . $open_part_count . "/" . $total_part_count . ")";
                } else {
                    $is_completed = strtotime($end_date) < strtotime($today);
                    if ($is_completed) {
                        switch ($end_action_flag) {
                            case Book::ALL_FREE:
                                $status = "완결(모두 공개)";
                                break;
                            case Book::ALL_CHARGED:
                                $status = "완결(모두 잠금)";
                                break;
                            case Book::SALES_CLOSED:
                                $status = "판매종료(파트 비공개)";
                                break;
                            case Book::ALL_CLOSED:
                                $status = "게시종료(전체 비공개)";
                                break;
                            default:
                                $status = "";
                        }
                    } else {
                        $status = "연재 시작 대기";
                    }
                }

                return $status;
            })
        );

        $header = array(
            'total_sales' => $total_sales,
            'total_sales_royalty' => $total_sales_royalty,
            'total_free_download' => $total_free_download,
            'total_charged_download' => $total_charged_download
        );

        return $app['twig']->render(
            '/admin/download_sales_list.twig',
            compact('search_date', 'header', 'download_sales')
        );
    }

    public function downloadSalesDetail(Request $req, Application $app, $b_id)
    {
        $begin_date = $req->get('begin_date', '0000-00-00');
        $end_date = $req->get('end_date', date('Y-m-d'));
        if ($begin_date == '') {
            $begin_date = '0000-00-00';
        }
        if ($end_date == '') {
            $end_date = date('Y-m-d');
        }
        $search_date = array(
            'begin_date' => $begin_date,
            'end_date' => $end_date
        );

        $total_sales = 0;
        $total_free_download = 0;
        $total_charged_download = 0;

        $download_sales = DownloadSales::get($b_id, $begin_date, $end_date);
        $download_sales_detail = DownloadSales::getPartSalesList($b_id, $begin_date, $end_date);
        foreach ($download_sales_detail as $dsd) {
            // 푸터에 들어갈 정보 계산
            $total_sales += $dsd['total_coin_amount'];
            $total_free_download += $dsd['free_download'];
            $total_charged_download += $dsd['charged_download'];
        }

        $footer = array(
            'total_sales' => $total_sales,
            'total_free_download' => $total_free_download,
            'total_charged_download' => $total_charged_download
        );

        return $app['twig']->render(
            'admin/download_sales_detail.twig',
            compact('search_date', 'download_sales', 'download_sales_detail', 'footer')
        );
    }
}
