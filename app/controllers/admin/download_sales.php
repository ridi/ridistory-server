<?php

use Silex\Application;
use Silex\ControllerProviderInterface;
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
        $total_sales = 0;
        $total_royalty_sales = 0;
        $total_free_download = 0;
        $total_charged_download = 0;

        $download_sales = DownloadSales::getWholeList();

        $today = strtotime("now");
        foreach ($download_sales as &$ds) {
            $is_ongoing = strtotime($ds['begin_date']) <= $today && strtotime($ds['end_date']) >= $today;
            if ($is_ongoing) {
                $ds['status'] = "연재중(".$ds['open_part_count']."/".$ds['total_part_count'].")";
            } else {
                switch ($ds['end_action_flag']) {
                    case 'ALL_FREE':
                        $ds['status'] = "완결(모두 공개)";
                        break;
                    case 'ALL_CHARGED':
                        $ds['status'] = "완결(모두 잠금)";
                        break;
                    case 'SALES_CLOSED':
                        $ds['status'] = "판매종료(파트 비공개";
                        break;
                    case 'ALL_CLOSED':
                        $ds['status'] = "게시종료(전체 비공개)";
                        break;
                }
            }

            $ds['begin_date'] = date("y.m.d", strtotime($ds['begin_date']));

            // 헤더에 들어갈 정보 계산
            $total_sales += $ds['total_sales'];
            $total_royalty_sales += $ds['total_sales'] * $ds['royalty_percent'];
            $total_free_download += $ds['free_download'];
            $total_charged_download += $ds['charged_download'];
        }

        $header = array(
            'total_sales' => $total_sales,
            'total_royalty_sales' => $total_royalty_sales,
            'total_free_download' => $total_free_download,
            'total_charged_download' => $total_charged_download
        );

        return $app['twig']->render(
            '/admin/download_sales_list.twig',
            compact('header', 'download_sales')
        );
    }

    public function downloadSalesDetail(Request $req, Application $app, $b_id)
    {
        $total_sales = 0;
        $total_free_download = 0;
        $total_charged_download = 0;

        $download_sales = DownloadSales::get($b_id);
        $download_sales_detail = DownloadSales::getPartSalesList($b_id);
        foreach ($download_sales_detail as &$dsd) {
            $dsd['total_coin_amount'] *= 100;

            // 푸터에 들어갈 정보 계산
            $total_sales += $dsd['total_coin_amount'];
            $total_free_download += $dsd['total_free_download'];
            $total_charged_download += $dsd['total_charged_download'];
        }

        $footer = array(
            'total_sales' => $total_sales,
            'total_free_download' => $total_free_download,
            'total_charged_download' => $total_charged_download
        );

        return $app['twig']->render(
            'admin/download_sales_detail.twig',
            compact('download_sales', 'download_sales_detail', 'footer')
        );
    }
}
