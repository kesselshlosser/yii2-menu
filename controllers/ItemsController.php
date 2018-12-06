<?php
/**
 * Created by PhpStorm.
 * User: john
 * Date: 12.11.2018
 * Time: 12:59
 */

namespace oboom\menu\controllers;
use Yii;
use yii\web\Controller;
use oboom\menu\models\Seo;
use oboom\menu\models\Menu;
use oboom\menu\models\MenuItems;
use yii\data\ArrayDataProvider;

class ItemsController extends Controller
{
    public function actionIndex($cat=null)
    {
        //var_dump($cat);
        $allCat = Menu::find()->asArray()->all();
        if(is_null($cat) || empty($cat)){
            $query = MenuItems::find()->joinWith('menu')->asArray()->all();
        }else{
            $query = MenuItems::find()->joinWith('menu')->where(["menu_items.menu_id"=>$cat])->asArray()->all();
        }

        $provider = new ArrayDataProvider([

            'allModels'=>$query,
            'pagination' => [
                'pageSize' => 20,
            ],
            'sort' => [
                'attributes' => ['id'],
            ],
        ]);


        return $this->render('index',[
            'items'=>$provider->getModels(),
            'pages'=>$provider->pagination,
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
                return $this->redirect(Yii::$app->request->referrer ? Yii::$app->request->referrer : Yii::$app->homeUrl);
            }
        }

        else{
            return $this->render('create', ['item'=>$item,'seo'=>$seo, 'menu'=>$menu, 'parent'=>$parent]);
        }
    }

    public function actionUpdate($id=null)
    {

        $item = MenuItems::find()->where(['=','menu_items.id',$id])->joinWith('seo')->limit(1)->one();
        $menu = Menu::find()->all();
        $parent = MenuItems::find()->where(['=','parent',0])->all();

        if ($item->load(Yii::$app->request->post()) &&
            $item['seo']->load(Yii::$app->request->post())) {

            if ($item->save() && $item['seo']->save()){
                return $this->goBack((!empty(Yii::$app->request->referrer) ? Yii::$app->request->referrer : null));
            }
        }

        else{
            return $this->render('update', ['item'=>$item, 'menu'=>$menu, 'parent'=>$parent]);
        }

    }

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

    public function actionRemove($id=null)
    {
        if(Yii::$app->request->isPost){
            $cat = MenuItems::findOne($id);
            if ($cat->delete()) {
                return $this->redirect(Yii::$app->request->referrer);
            }
        }
    }

    public static function Menu($id){
        return MenuItems::find()->joinWith('seo')->joinWith('menu')->where(['menu_items.status'=>1,'menu.id'=>$id , 'menu.status'=>1])->asArray()->all();
    }


    protected function getByUrl($url){

        return Seo::find()->joinWith('items')->where(['seo.url'=>$url, 'menu_items.status'=>1])->asArray()->limit(1)->one();
    }
}

