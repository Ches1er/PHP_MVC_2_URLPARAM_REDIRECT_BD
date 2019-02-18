<?php
/**
 * Created by PhpStorm.
 * User: mamedov
 * Date: 11.02.2019
 * Time: 19:35
 */

namespace app\controllers;


use core\base\Controller;
use core\base\TemplateView;
use core\base\View;
use core\db\DBQueryBuilder;

class Main extends Controller
{
    public function actionIndex(){
        $view = new TemplateView("main","templates/def");
        $qb = new DBQueryBuilder();
/*        $qb->delete("users")->where("user_id",">",10)->
        andWhere("user_id","<",15)->execDel();*/
        /*$qb->insert("todo",["note_name","desc","user_id"],["sell","sell smth",3]);*/
        /*$qb->update("todo",["desc"],["sell smth"])->where("note_id",4)->execUpdate();*/
        echo "<pre>";
        $res = $qb->select(["user_id","user_name"])->from("users")->limit(2,1)->execSelect();
        print_r($res);
        $view->hh="sdf";
        return $view;
    }
}