<?php
/**
 * Created by PhpStorm.
 * User: john
 * Date: 12.11.2018
 * Time: 12:59
 */

namespace oboom\menu\controllers;
use Yii;
use yii\helpers\Json;
use yii\web\Controller;
use oboom\menu\models\Seo;
use oboom\menu\models\Menu;
use oboom\menu\models\MenuItems;
use yii\data\ArrayDataProvider;

class ItemsController extends Controller
{
    public function actionIndex($cat=null)
    {
        \yii\helpers\Url::remember();

        $allCat = Menu::find()->asArray()->all();
        if(is_null($cat) || empty($cat)){
            //$query = MenuItems::find()->joinWith('menu')->asArray()->all();
        }else{
            $data = [];
            $query = MenuItems::find()->joinWith('menu')->where(["menu_items.menu_id"=>$cat,'menu_items.parent'=>0])->orderBy('menu_items.sort')->all();
            foreach ($query as $item){
                $data[]=['parent'=>$item, 'child'=>MenuItems::find()->select('id, label, status, sort, parent')->where(['parent'=>$item->id])->orderBy('sort')->all()];
            }
        }



        return $this->render('index',[
            'items'=>$data,

            'cats'=>$allCat,
            'catId'=>$cat]);
    }




    public function actionCreate()
    {

        $item = new MenuItems();
        $seo = new Seo();
        $menu = Menu::find()->all();
        $parent = MenuItems::find()->where(['=','parent',0])->all();

        if ($item->load(Yii::$app->request->post()) && $seo->load(Yii::$app->request->post())) {

            if($seo->save()){
                $item->seo_id = $seo->id;
                $item->save();
                return $this->goBack();
            }
        }

        else{
            return $this->render('create', ['item'=>$item,'seo'=>$seo, 'menu'=>$menu, 'parent'=>$parent]);
        }
    }

    public function actionUpdate($id=null)
    {
        //var_dump(Yii::$app->request->referrer);
        $item = MenuItems::find()->where(['=','menu_items.id',$id])->joinWith('seo')->limit(1)->one();
        $menu = Menu::find()->all();
        $parent = MenuItems::find()->where(['=','parent',0])->all();

        if ($item->load(Yii::$app->request->post()) &&
            $item['seo']->load(Yii::$app->request->post())) {

            if ($item->save() && $item['seo']->save()){
                return $this->goBack();
                //return $this->redirect(Yii::$app->request->referrer ? Yii::$app->request->referrer : Yii::$app->homeUrl);
            }
        }

        else{
            return $this->render('update', ['item'=>$item, 'menu'=>$menu, 'parent'=>$parent]);
        }

    }

    public function actionRemove($id=null)
    {
        if(Yii::$app->request->isPost){
            $cat = MenuItems::findOne($id);
            if ($cat->delete()) {
                return $this->redirect(Yii::$app->request->referrer);
            }
        }
    }

    //ajax update status of publication
    public function actionStatus($json=null)
    {

        if(Yii::$app->request->isAjax){
            $cat = Menu::findOne(Yii::$app->request->post(id));
            if ($cat->status == 0) {
                $cat->status =1;
            }
            else {
                $cat->status =0;
            }
            if ($cat->save()) {
                return $this->asJson([
                    'status' => true,
                ]);
            }
            else {
                return $this->asJson([
                    'status' => false,
                ]);
            }
        }
        else {
            return $this->asJson(['status'=>'Access denied']);
        }
    }

    //ajax update sort
    public function actionSort($json=null)
    {
        if(Yii::$app->request->isAjax){

            $json = Json::decode($json);
            //return Json::encode($json[0]);
            $i=-1;
            foreach ($json[0] as $data){
                ++$i;
                $parent = MenuItems::findOne($data['id']);
                $parent->sort = $i;
                $parent->parent = 0;
                $parent->save();



                if(count($data['children'][0])>0){
                    $j=-1;
                    foreach ($data['children'][0] as $child){
                        ++$j;
                        $childItem = MenuItems::findOne($child['id']);
                        if(!is_null($childItem)) {
                            $childItem->sort = $j;
                            $childItem->parent=$parent->id;
                            $childItem->save();

                        }

                    }

                }
            }

            return $this->asJson(['status'=>true]);
        }
        else {
            return $this->asJson(['status'=>'Access denied']);
        }
    }

    public static function Menu($menu_id,$level=1){

        if($level==2){
            $data = [];
            $query = MenuItems::find()->joinWith('menu')->joinWith('seo')->where(["menu_items.menu_id"=>$menu_id,'menu_items.parent'=>0,'menu.status'=>1])->orderBy('menu_items.sort')->all();
            foreach ($query as $item){
                $data[]=['parent'=>$item, 'child'=>MenuItems::find()->joinWith('seo')->joinWith('menu')
                        ->where(['menu_items.parent'=>$item->id,'menu_items.status'=>1])
                        ->orderBy('sort')->asArray()->all()];
            }

            return $data;
        }
        return MenuItems::find()->joinWith('seo')->joinWith('menu')
                                ->where(['menu_items.status'=>1,'menu.id'=>$menu_id , 'menu.status'=>1, 'menu_items.parent'=>0])
                                ->orderBy(['menu_items.sort'=>SORT_ASC])->asArray()->all();
        if(!is_null($item_id)){}
    }

    protected function getByUrl($url){
        return Seo::find()->joinWith('items')->where(['seo.url'=>$url, 'menu_items.status'=>1])->limit(1)->one();
    }
}

