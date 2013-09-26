<?php

use Silex\Application;
use Silex\ControllerProviderInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class AdminStoryPlusBookIntroControllerProvider implements ControllerProviderInterface
{
    public function connect(Application $app)
    {
        $admin = $app['controllers_factory'];

        $admin->get('add', array($this, 'storyPlusBookIntroAdd'));
        $admin->get('{id}', array($this, 'storyPlusBookIntroDetail'));
        $admin->get('{id}/delete', array($this, 'storyPlusBookIntroDelete'));
        $admin->post('{id}/edit', array($this, 'storyPlusBookIntroEdit'));

        return $admin;
    }

    public function storyPlusBookIntroDetail(Request $req, Application $app, $id)
    {
        $intro = StoryPlusBookIntro::get($id);
        $intro_type_names = array('BOOK_INTRO', 'AUTHOR_INTRO', 'PHRASE', 'RECOMMEND');
        return $app['twig']->render(
            'admin/storyplusbook_intro_detail.twig',
            array(
                'intro' => $intro,
                'intro_type_names' => $intro_type_names
            )
        );
    }

    public function storyPlusBookIntroAdd(Request $req, Application $app)
    {
        $b_id = $req->get('b_id');
        $intro_id = StoryPlusBookIntro::create($b_id);
        $app['session']->set('alert', array('success' => '소개가 추가되었습니다.'));
        return $app->redirect('/admin/storyplusbook_intro/' . $intro_id);
    }

    public function storyPlusBookIntroEdit(Request $req, Application $app, $id)
    {
        $inputs = $req->request->all();
        $intro = StoryPlusBookIntro::get($id);
        StoryPlusBookIntro::update($id, $inputs);
        $app['session']->set('alert', array('info' => '소개가 수정되었습니다.'));
        return $app->redirect('/admin/storyplusbook/' . $intro['b_id']);
    }

    public function storyPlusBookIntroDelete(Request $req, Application $app, $id)
    {
        $intro = StoryPlusBookIntro::get($id);
        StoryPlusBookIntro::delete($id);
        $app['session']->set('alert', array('info' => '소개가 삭제되었습니다.'));
        return $app->redirect('/admin/storyplusbook/' . $intro['b_id']);
    }
}
