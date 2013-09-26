<?php

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminStoryPlusBookControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('list', array($this, 'storyPlusBookList'));
        $admin->get('add', array($this, 'storyPlusBookAdd'));
        $admin->get('{id}', array($this, 'storyPlusBookDetail'));
        $admin->post('{id}/delete', array($this, 'storyPlusBookDelete'));
        $admin->post('{id}/edit', array($this, 'storyPlusBookEdit'));

        return $admin;
    }

    public function storyPlusBookList(Request $req, Application $app)
    {
        $books = StoryPlusBook::getWholeList();
        return $app['twig']->render('admin/storyplusbook_list.twig', array('books' => $books));
    }

    public function storyPlusBookDetail(Request $req, Application $app, $id)
    {
        $book = StoryPlusBook::get($id);
        $intros = StoryPlusBookIntro::getListByBid($id);
        $badge_names = array('NONE', 'BESTSELLER', 'FAMOUSAUTHOR', 'HOTISSUE', 'NEW');

        $app['twig']->addFunction(
            new Twig_SimpleFunction('today', function () {
                return date('Y-m-d');
            })
        );

        return $app['twig']->render(
            'admin/storyplusbook_detail.twig',
            array(
                'book' => $book,
                'intros' => $intros,
                'badge_names' => $badge_names
            )
        );
    }

    public function storyPlusBookAdd(Request $req, Application $app)
    {
        $b_id = StoryPlusBook::create();
        $app['session']->set('alert', array('success' => '책이 추가되었습니다.'));
        return $app->redirect('/admin/storyplusbook/' . $b_id);
    }

    public function storyPlusBookEdit(Request $req, Application $app, $id)
    {
        $inputs = $req->request->all();

        // 상세 정보는 별도 테이블로
        $intros = array('b_id' => $id);
        array_move_keys(
            $inputs,
            $intros,
            array(
                'intro_type' => 'type',
                'intro_descriptor' => 'intro_descriptor'
            )
        );

        StoryPlusBook::update($id, $inputs);

        $app['session']->set('alert', array('info' => '책이 수정되었습니다.'));
        return $app->redirect('/admin/storyplusbook/' . $id);
    }

    public function storyPlusBookDelete(Request $req, Application $app, $id)
    {
        // TODO
        $intros = StoryPlusBookIntro::getListByBid($id);
        if (count($intros)) {
            return $app->json(array('error' => 'Part가 있으면 책을 삭제할 수 없습니다.'));
        }
        StoryPlusBook::delete($id);
        $app['session']->set('alert', array('info' => '책이 삭제되었습니다.'));
        return $app->json(array('success' => true));
    }
}
