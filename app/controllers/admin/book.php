<?php

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Story\Model\Book;
use Story\Model\CpAccount;
use Story\Model\RecommendedBook;
use Story\Model\Part;

class AdminBookControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('add', array($this, 'addBook'));
        $admin->get('list', array($this, 'bookList'));
        $admin->get('{id}', array($this, 'bookDetail'));
        $admin->post('{id}/delete', array($this, 'deleteBook'));
        $admin->post('{id}/edit', array($this, 'editBook'));

        return $admin;
    }

    public function addBook(Request $req, Application $app)
    {
        $b_id = Book::create();

        $app['session']->getFlashBag()->add('alert', array('success' => '책이 추가되었습니다.'));
        return $app->redirect('/admin/book/' . $b_id);
    }

    public function bookList(Request $req, Application $app)
    {
        $books = Book::getWholeList();
        $today = date('Y-m-d H:i:s');
        foreach ($books as &$book) {
            $progress = 0;
            $progress2 = 0;
            if ($book['total_part_count'] > 0) {
                $progress = 100 * $book['open_part_count'] / $book['total_part_count'];
                $progress2 = 100 * $book['uploaded_part_count'] / $book['total_part_count'];
            }
            $book['progress'] = $progress . '%';
            $book['progress2'] = $progress2 . '%';

            if (strtotime($book['end_date']) < strtotime($today)) {
                // 완결
                switch($book['end_action_flag']) {
                    case Book::ALL_FREE:
                        $book['status'] = '모두공개';
                        break;
                    case Book::ALL_CHARGED:
                        $book['status'] = '모두잠금';
                        break;
                    case Book::SALES_CLOSED:
                        $book['status'] = '판매종료';
                        break;
                    case Book::ALL_CLOSED:
                        $book['status'] = '게시종료';
                        break;
                }
            } else {
                if ($book['open_part_count'] <= 0) {
                    if (strtotime($book['begin_date']) > strtotime('now')) {
                        // Coming Soon 예고 시작 전.
                        $book['status'] = '';
                    } else {
                        // Coming Soon 예고중
                        $book['status'] = '예고중';
                    }
                } else {
                    // 연재중
                    $book['status'] = '연재중';
                }
            }
        }
        return $app['twig']->render('admin/book_list.twig', array('books' => $books));
    }

    public function bookDetail(Request $req, Application $app, $id)
    {
        $book = Book::get($id);
        $cp_accounts = CpAccount::getCpList();
        $recommended_books = RecommendedBook::getRecommendedBookListByBid($id);

        $today = date('Y-m-d H:00:00');

        $active_lock = $book['is_active_lock'];
        $is_completed = ($book['is_completed'] == 1 || strtotime($book['end_date']) < strtotime($today) ? 1 : 0);
        $parts = Part::getListByBid($id, false, $active_lock, $is_completed, $book['end_action_flag']);

        $is_completed = strtotime($today) > strtotime($book['end_date']);

        foreach ($parts as &$part) {
            // 1화가 아직 시작하지 않은 경우에는, 모두 '비공개'로 변경
            if ($part['seq'] <= 1 && strtotime($part['begin_date']) > strtotime($today)) {
                foreach ($parts as &$temp_part) {
                    $temp_part['status'] = '비공개';
                }
                break;
            }

            if ($part['is_locked'] == 0) {
                $part['status'] = '공개';
            } else {
                $part['status'] = '잠금';
            }

            if ($is_completed) {
                if ($book['end_action_flag'] == Book::SALES_CLOSED || $book['end_action_flag'] == Book::ALL_CLOSED) {
                    $part['status'] = '비공개';
                }
            } else {
                if ($active_lock) {
                    if (strtotime($part['begin_date']) > strtotime($today . ' + 14 days')) {
                        $part['status'] = '비공개';
                    }
                } else {
                    if (strtotime($today) < strtotime($part['begin_date']) || strtotime($today) > strtotime($part['end_date'])) {
                        $part['status'] = '비공개';
                    }
                }
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
                'recommended_books' => $recommended_books,
                'intro' => $intro,
            )
        );
    }

    public function deleteBook(Request $req, Application $app, $id)
    {
        $parts = Part::getListByBid($id);
        if (count($parts)) {
            return $app->json(array('error' => 'Part가 있으면 책을 삭제할 수 없습니다.'));
        }
        Book::delete($id);
        Book::deleteIntro($id);
        RecommendedBook::deleteByBid($id);
        $app['session']->getFlashBag()->add('alert', array('info' => '책이 삭제되었습니다.'));
        return $app->json(array('success' => true));
    }

    public function editBook(Request $req, Application $app, $id)
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
}
