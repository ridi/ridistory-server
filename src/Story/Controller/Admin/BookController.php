<?php
namespace Story\Controller\Admin;

use Silex\Application;
use Silex\ControllerProviderInterface;
use Story\Entity\BookNoticeFactory;
use Symfony\Component\HttpFoundation\Request;
use Story\Entity\RecommendedBookFactory;
use Story\Model\Book;
use Story\Model\CpAccount;
use Story\Model\Part;
use Twig_SimpleFunction;
use Exception;

class BookController implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('manage_score', array($this, 'onGoingBookList'));
        $admin->post('manage_score/edit', array($this, 'editBookScoreList'));

        $admin->get('add', array($this, 'addBook'));
        $admin->get('list', array($this, 'bookList'));
        $admin->get('{id}', array($this, 'bookDetail'));
        $admin->post('{id}/delete', array($this, 'deleteBook'));
        $admin->post('{id}/edit', array($this, 'editBook'));
        $admin->post('{id}/calc_part_date_auto', array($this, 'calculatePartDateAutomatically'));

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
                    if (strtotime($book['begin_date']) > strtotime($today)) {
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
        return $app['twig']->render('admin/book/book_list.twig', array('books' => $books));
    }

    public function bookDetail(Request $req, Application $app, $id)
    {
        $book = Book::get($id);
        $cp_accounts = CpAccount::getCpList();
        $recommended_books = RecommendedBookFactory::getRecommendedBookListByBid($id, true);

        $today = date('Y-m-d H:i:s');

        $active_lock = $book['is_active_lock'];
        $is_completed = strtotime($book['end_date']) < strtotime($today);
        $parts = Part::getListByBid($id, false, $active_lock, $is_completed, $book['end_action_flag'], $book['lock_day_term']);

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
                    if (strtotime($part['begin_date']) > strtotime($today . ' + ' . $book['lock_day_term'] . ' days')) {
                        $part['status'] = '비공개';
                    }
                } else {
                    if (strtotime($today) < strtotime($part['begin_date']) || strtotime($today) > strtotime($part['end_date'])) {
                        $part['status'] = '비공개';
                    }
                }
            }

            if (strtotime($part['begin_date']) >= strtotime($part['end_date'])) {
                $part['status'] = '오류';
            }
        }

        $book_notices = BookNoticeFactory::getList($id, false);

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
            'admin/book/book_detail.twig',
            array(
                'book' => $book,
                'cp_accounts' => $cp_accounts,
                'book_notices' => $book_notices,
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
        RecommendedBookFactory::deleteByBid($id);
        $app['session']->getFlashBag()->add('alert', array('info' => '책이 삭제되었습니다.'));

        // 캐시 삭제
        $app['cache']->delete('book_list_1');
        $app['cache']->delete('book_list_2_0');
        $app['cache']->delete('book_list_2_1');
        $app['cache']->delete('book_list_3_0');
        $app['cache']->delete('book_list_3_1');
        $app['cache']->delete('book_list_4_0');
        $app['cache']->delete('book_list_4_1');
        $app['cache']->delete('completed_book_list_0');
        $app['cache']->delete('completed_book_list_1');
        $app['cache']->delete('part_list_0_0_' . $id);
        $app['cache']->delete('part_list_0_1_' . $id);
        $app['cache']->delete('part_list_1_0_' . $id);
        $app['cache']->delete('part_list_1_1_' . $id);

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

        // 캐시 삭제
        $app['cache']->delete('book_list_1');
        $app['cache']->delete('book_list_2_0');
        $app['cache']->delete('book_list_2_1');
        $app['cache']->delete('book_list_3_0');
        $app['cache']->delete('book_list_3_1');
        $app['cache']->delete('book_list_4_0');
        $app['cache']->delete('book_list_4_1');
        $app['cache']->delete('completed_book_list_0');
        $app['cache']->delete('completed_book_list_1');
        $app['cache']->delete('part_list_0_0_' . $id);
        $app['cache']->delete('part_list_0_1_' . $id);
        $app['cache']->delete('part_list_1_0_' . $id);
        $app['cache']->delete('part_list_1_1_' . $id);

        return $app->redirect('/admin/book/' . $id);
    }

    public function calculatePartDateAutomatically(Request $req, Application $app, $id)
    {
        $book = Book::get($id);
        $parts = Part::getListByBid($id, false, $book['is_active_lock'], false, $book['end_action_flag'], $book['lock_day_term']);

        try {
            $criteria_part = null;
            $criteria_p_id = $req->get('criteria_p_id', 0);
            while(!empty($parts)) {
                $part = array_shift($parts);
                if ($part['id'] == $criteria_p_id) {
                    $criteria_part = $part;
                    break;
                }
            }
            if (empty($parts) || $criteria_part == null) {
                throw new Exception('기준이 되는 파트가 해당 책의 파트 목록에 존재하지 않습니다.');
            }

            // 기준이 되는 파트를 첫 파트로 생각하고 날짜를 계산한다.
            $begin_date = $criteria_part['begin_date'];
            $end_date = $criteria_part['end_date'];
            $day_of_week  = date('w', strtotime($begin_date));  // Sun(0) ~ Sat(6)

            if ($begin_date == null || $end_date == null || $book['upload_days'] == 0) {
                throw new Exception('연재 요일, 첫 번째 파트의 시작일/종료일을 모두 정확히 입력해주세요.');
            }

            $upload_days = array();
            for ($i=0; $i<7; $i++) {
                $upload_days[] = ($book['upload_days'] & (1 << $i)) ? 1 : 0;
            }

            // 연재요일보다 하루 전에 업로드 되므로, 인덱스를 1씩 앞으로 이동.
            $first_element = array_shift($upload_days);
            array_push($upload_days, $first_element);

            if ($upload_days[$day_of_week] != 1) {
                throw new Exception('첫 번째 파트의 연재 요일이, 책의 연재 요일과 다릅니다.');
            }

            $new_begin_date = $begin_date;
            $day_index = $day_of_week;

            $app['db']->beginTransaction();
            try {
                foreach ($parts as $part) {
                    $loop_count = 0;    // 혹시나 모를 무한 루프를 방지하기 위한 플래그.
                    while(true) {
                        $new_begin_date = date('Y-m-d H:i:s', strtotime($new_begin_date . ' + 1 day'));
                        if (++$day_index > 6) {
                            $day_index = 0;
                        }

                        if ($upload_days[$day_index] == 1) {
                            Part::update($part['id'], array('begin_date' => $new_begin_date, 'end_date' => $end_date));
                            break;
                        }

                        $loop_count++;
                        if ($loop_count > 7) {
                            // 최소 1주일에 1회이상 연재를 해야하는데, 그러지 않을 경우 무한루프로 간주하고 break.
                            throw new Exception('연재 요일 계산 오류가 발생하였습니다. (무한루프)');
                        }
                    }
                }

                $app['db']->commit();
            } catch (Exception $e) {
                $app['db']->rollback();
                throw new Exception('파트의 시작일/종료일을 업데이트하는 도중 오류가 발생했습니다.');
            }

            $app['session']->getFlashBag()->add('alert', array('info' => '파트 시작일/종료일이 자동으로 계산되어 적용되었습니다.'));
            return $app->json(array('success' => true));
        } catch (Exception $e) {
            $app['session']->getFlashBag()->add('alert', array('error' => $e->getMessage()));
            return $app->json(array('success' => false));
        }
    }

    public function onGoingBookList(Request $req, Application $app)
    {
        $books = Book::getOpenedBookList(false);

        // 작품 점수 순으로 정렬
        usort($books, function ($a, $b) {
                if ($a['score'] == $b['score']) {
                    return 0;
                }

                return ($a['score'] > $b['score'] ? -1 : 1);
            }
        );

        return $app['twig']->render('admin/book/manage_book_score.twig', array('books' => $books));
    }

    public function editBookScoreList(Request $req, Application $app)
    {
        $orders = $req->get('orders');

        foreach ($orders as $b_id => $order) {
            // 순서(오름차순) -> 점수(내림차순)으로의 변환.
            $order = (count($orders) + 1) - $order;

            Book::update($b_id, array('score' => $order));
        }

        $app['session']->getFlashBag()->add('alert', array('info' => '인기순이 수정되었습니다.'));

        // 캐시 삭제
        $app['cache']->delete('book_list_1');
        $app['cache']->delete('book_list_2_0');
        $app['cache']->delete('book_list_2_1');
        $app['cache']->delete('book_list_3_0');
        $app['cache']->delete('book_list_3_1');
        $app['cache']->delete('book_list_4_0');
        $app['cache']->delete('book_list_4_1');

        return $app->redirect('/admin/book/manage_score');
    }
}