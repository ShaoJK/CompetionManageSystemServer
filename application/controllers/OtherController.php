<?php

/**
 * Created by PhpStorm.
 * User: sjk
 * Date: 2016/11/26
 * Time: 22:21
 */
class OtherController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        //连接数据库
        $this->load->database();
        $this->load->library('session');

    }
    /**
     * 获得公告信息
     */
    public function getAnnouncementList(){
        $sql = "select * from t_bulletin";
        $query = $this->db->query($sql);
        $result = $query->result();
        $result = "{success:'true',msg:'请求成功',list:" . json_encode($result) . "}";
        echo $result;
    }
}

