<?php

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Story\Model\Book;
use Story\Model\CpAccount;
use Story\Model\RecommendBook;
use Story\Model\Part;

class AdminBookControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('list', array($this, 'bookList'));
        $admin->get('add', array($this, 'bookAdd'));
        $admin->get('{id}', array($this, 'bookDetail'));
        $admin->post('{id}/delete', array($this, 'bookDelete'));
        $admin->post('{id}/edit', array($this, 'bookEdit'));

        return $admin;
    }

    public function bookList(Request $req, Application $app)
    {
        $books = Book::getWholeList();
        foreach ($books as &$book) {
            $progress = 0;
            $progress2 = 0;
            if ($book['total_part_count'] > 0) {
                $progress = 100 * $book['open_part_count'] / $book['total_part_count'];
                $progress2 = 100 * ($book['uploaded_part_count'] - $book['open_part_count']) / $book['total_part_count'];
            }
            $book['progress'] = $progress . '%';
            $book['progress2'] = $progress2 . '%';
        }
        return $app['twig']->render('admin/book_list.twig', array('books' => $books));
    }

    public function bookDetail(Request $req, Application $app, $id)
    {
        $book = Book::get($id);
        $cp_accounts = CpAccount::getCpList();
        $recommend_books = RecommendBook::getRecommendBookListByBid($id);

        $active_lock = $book['is_active_lock'];
        $parts = Part::getListByBid($id, false, $active_lock, true);

        $today = date('Y-m-d H:00:00');
        foreach ($parts as &$part) {
            if (strtotime($part['begin_date']) <= strtotime($today)) {
                $part['status'] = "공개";
            } else if ($active_lock && strtotime($part['begin_date']) < strtotime($today." + 13 days")) {
                $part['status'] = "잠금";
            } else {
                $part['status'] = "비공개";
            }
        }

        $intro = Book::getIntro($id);
        if ($intro === false) {
            $intro = array('b_id' => $id, 'description' => '', 'about_author' => '');
            Book::createIntro($intro);
        }

        $app['twig']->addFunction(
            new Twig_SimpleFunction('today', function () {
                return date('Y-m-d');
            })
        );

        return $app['twig']->render(
            'admin/book_detail.twig',
            array(
                'book' => $book,
                'cp_accounts' => $cp_accounts,
                'parts' => $parts,
                'recommend_books' => $recommend_books,
                'intro' => $intro,
            )
        );
    }

    public function bookAdd(Request $req, Application $app)
    {
        $b_id = Book::create();

        $app['session']->getFlashBag()->add('alert', array('success' => '책이 추가되었습니다.'));
        return $app->redirect('/admin/book/' . $b_id);
    }

    public function bookEdit(Request $req, Application $app, $id)
    {
        $inputs = $req->request->all();

        // 연재 요일
        $upload_days = 0;
        if (isset($inputs['upload_days'])) {
            foreach ($inputs['upload_days'] as $v) {
                $upload_days += intval($v);
            }
        }
        $inputs['upload_days'] = $upload_days;

        $inputs['adult_only'] = isset($inputs['adult_only']);
        $inputs['is_active_lock'] = isset($inputs['is_active_lock']);

        // 상세 정보는 별도 테이블로
        $intro = array('b_id' => $id);

        $intro['description'] = $inputs['intro_description'];
        $intro['about_author'] = $inputs['intro_about_author'];
        unset($inputs['intro_description']);
        unset($inputs['intro_about_author']);

        Book::update($id, $inputs);
        Book::updateIntro($id, $intro);

        $app['session']->getFlashBag()->add('alert', array('info' => '책이 수정되었습니다.'));
        return $app->redirect('/admin/book/' . $id);
    }

    public function bookDelete(Request $req, Application $app, $id)
    {
        $parts = Part::getListByBid($id);
        if (count($parts)) {
            return $app->json(array('error' => 'Part가 있으면 책을 삭제할 수 없습니다.'));
        }
        Book::delete($id);
        $app['session']->getFlashBag()->add('alert', array('info' => '책이 삭제되었습니다.'));
        return $app->json(array('success' => true));
    }
}
