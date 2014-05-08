<?php
namespace Story\Controller;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Entity\RecommendedBookFactory;
use Story\Model\Notice;
use Symfony\Component\HttpFoundation\Request;

use Story\Model\Book;
use Story\Model\Part;
use Story\Model\PartComment;

class WebController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        /**
         * @var $web \Silex\ControllerCollection
         */
        $web = $app['controllers_factory'];

        $web->get('/book/{b_id}/intro', array($this, 'bookIntro'));

        $web->post('/comment/add', array($this, 'addComment'));
        $web->get('/comment/list', array($this, 'commentList'));
        $web->get('/comment/{c_id}/delete', array($this, 'deleteComment'));

        $web->get('/notice', array($this, 'noticeList'));
        $web->get('/notice/{n_id}', array($this, 'noticeDetail'));

        $web->get('/banner', array($this, 'banner'));

        $web->get('/preview/{p_id}/{title}', array($this, 'previewPart'));

        $web->get('/recommended_book/{b_id}', array($this, 'recommendedBook'));

        return $web;
    }

    /*
     * Book Intro
     */
    public function bookIntro(Application $app, $b_id)
    {
        $book = Book::get($b_id);
        $book['intro'] = Book::getIntro($b_id);
        return $app['twig']->render('/book_intro.twig', array('book' => $book));
    }

    /*
     * Comment
     */
    public function addComment(Request $req, Application $app)
    {
        $is_admin = $req->get('is_admin', 0);
        $p_id = $req->get('p_id');
        $device_id = $req->get('device_id');
        $nickname = trim($req->get('nickname'));
        $comment = trim($req->get('comment'));

        if (empty($nickname) || empty($comment)) {
            return alert_and_back('닉네임이나 댓글 내용이 없습니다.');
        }

        if ($nickname == '리디스토리' && $is_admin == 0) {
            return alert_and_back('리디스토리는 닉네임으로 사용하실 수 없습니다.');
        }

        $ip = ip2long($_SERVER['REMOTE_ADDR']);

        // TODO: abuse detection

        PartComment::add($p_id, $device_id, $nickname, $comment, $ip);

        if ($is_admin == 1) {
            $app['session']->getFlashBag()->add('alert', array('success' => '운영자 댓글이 추가되었습니다.'));
        }
        return $app->redirect($req->headers->get('Referer'));
    }

    public function commentList(Request $req, Application $app)
    {
        $p_id = $req->get('p_id');
        $part = Part::get($p_id);
        if ($part === false) {
            return '오류가 발생하였습니다. 다시 시도해주세요.';
        }

        $device_id = $req->get('device_id');

        $num_comments = PartComment::getCommentCount($p_id);
        $comments = PartComment::getList($p_id);

        return $app['twig']->render(
            '/comment.twig',
            array(
                'part' => $part,
                'device_id' => $device_id,
                'num_comments' => $num_comments,
                'comments' => $comments
            )
        );
    }

    public function deleteComment(Request $req, Application $app, $c_id)
    {
        PartComment::delete($c_id);
        return $app->redirect($req->headers->get('Referer'));
    }

    /*
     * Notice
     */
    public function noticeList(Application $app)
    {
        $notice = Notice::getList(true);
        return $app['twig']->render('/notice.twig', array('notice' => $notice));
    }

    public function noticeDetail(Application $app, $n_id)
    {
        $notice_item = Notice::get($n_id, true);
        return $app['twig']->render('/notice_item.twig', array('notice_item' => $notice_item));
    }

    /*
     * Banner
     */
    public function banner(Request $req, Application $app)
    {
        $platform = $req->get('platform');
        $banners = $app['cache']->fetch(
            'banners',
            function () use ($app) {
                return $app['db']->fetchAll('select * from banner where is_visible = 1 order by reg_date desc');
            },
            60 * 30
        );
        return $app['twig']->render(
            '/banner.twig',
            array(
                'platform' => $platform,
                'banners' => $banners,
            )
        );
    }

    /*
     * Preview
     */
    public function previewPart(Request $req, Application $app, $p_id, $title)
    {
        $p = new Part($p_id);
        if (!$p->isOpened()) {
            $app->abort(404);
        }

        return $app->redirect('http://preview.ridibooks.com/' . $p->getStoreId() . '?mobile');
    }

    /*
     * Recommended Book
     */
    public function recommendedBook(Request $req, Application $app, $b_id)
    {
        $recommended_books = $app['cache']->fetch(
            'recommended_book_list_' . $b_id,
            function () use ($b_id) {
                return RecommendedBookFactory::getRecommendedBookListByBid($b_id, false);
            },
            60 * 10
        );

        return $app['twig']->render('/recommended_book.twig', array('books' => $recommended_books));
    }
}

function alert_and_back($msg)
{
    $r = '<!doctype html><html><head><meta charset="utf-8"><meta http-equiv="X-UA-Compatible" content="IE=Edge,chrome=1"></head><body><script>';
    $r .= "alert(" . json_encode($msg) . ");";
    $r .= "history.go(-1);";
    $r .= "</script></body></html>";
    return $r;
}
