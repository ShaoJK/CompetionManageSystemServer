<?php

/**
 * Created by PhpStorm.
 * User: SJK
 * Date: 2016/9/16
 * Time: 11:26
 */
class AndroidControler extends CI_Controller
{
    static $ROOT_RESOURCE = 'http://192.168.1.103/jc/application/resource';

    public function __construct()
    {
        parent::__construct();
        //连接数据库
        $this->load->database();
    }

    //安卓登录,如果登录成功则返回用户的相关信息，否则返回登录失败
    public function login()
    {
        $userName = $_POST['username'];
        $userPwd = $_POST['password'];
        //$userName = "admin";
        //$userPwd = md5("admin");
        $sql = "select * from T_User where userName = '$userName' and userPwd='$userPwd'";
        $query = $this->db->query($sql);
        if (count($query->result()) > 0) {
            $this->getUserInfo($userName);//获取用户相关信息
            echo "登录成功" . $this->getUserInfo($userName);
        } else {
            echo "登录失败";
        }
    }

    //保存用户头像
    public function saveUserHeadImg()
    {
        $user_Name = $_POST['username'];
        //检测是否存在该文件夹，若没有则创建
        $upload_path = APPPATH . 'resource\user_head_img\\' . $user_Name;;
        if (!file_exists($upload_path)) {
            mkdir($upload_path, true);
        }
        $config['upload_path'] = $upload_path;
        $config['allowed_types'] = '*';
        $config['max_size'] = 1024;
        $config['max_width'] = 1024;
        $config['max_height'] = 768;
        $this->load->library('upload', $config);
        //执行上传文件
        if (!$this->upload->do_upload('userheadimg')) {
            $error = array('error' => $this->upload->display_errors());
            echo '上传失败' . json_encode($error);
        } else {
            $data = array('upload_data' => $this->upload->data());
            echo '上传成功' . json_encode($data);
        }
    }

    //获得用户信息
    public function getUserInfo($userName)
    {
        $sql = "select * from T_User where userName = '$userName'";
        //echo $sql.'<br/>';
        $query = $this->db->query($sql);
        $userInfo = json_encode($query->result()[0]);
        return $userInfo;
    }

    public function getPath()
    {
        $path = APPPATH . "resource\user_head_img";
        if (!file_exists($path)) {
            mkdir($path, true);
        }
    }

    //根据项目名称查找项目
    public function searchPlanByName()
    {
        $planName = $_POST['planName'];
        //$planName = '测试';
        if (!empty($planName)) {
            $sql = "select * from T_Plan where planName = '$planName'";
            $query = $this->db->query($sql);
            if (count($query->result()) > 0) {//如果查询结果不为空
                echo '有计划#' . json_encode($query->result());
            } else {
                $sql2 = "select * from T_Plan where planName like '%$planName%'";
                $query2 = $this->db->query($sql2);
                echo '无计划#' . json_encode($query2->result());
            }
        }
    }

    //查找已参加的项目
    public function getJoinedPlan()
    {
        $userName = $_POST['userName'];
        $sql = "select a.planName,a.participation,b.* from T_Plan a,T_UserJoinPlan b where a.planID = b.planID and b.userName = '$userName'";
        $query = $this->db->query($sql);
        echo json_encode($query->result());
    }

    //查找具体参与项目设置信息
    public function getDetailPlanSetttingInfo()
    {
        $userName = $_POST['userName'];
        $planID = $_POST['planID'];
//        $userName = 'admin';
//        $planID = '0';
        $sql = "select a.planName,a.participation,b.* from T_Plan a,T_UserJoinPlan b where a.planID = b.planID and b.userName = '$userName' and b.planID = $planID";
        $query = $this->db->query($sql);
        echo json_encode($query->result()[0]);
    }

    //查找热门的项目(参与人数前10)
    public function getHotPlan()
    {
        $sql = 'select * from T_Plan order by participation desc LIMIT 10';
        $query = $this->db->query($sql);
        echo json_encode($query->result());
    }

    //根据Sql语句查询
    public function getDataBySql()
    {
        $sql = $_POST['sql'];
        $query = $this->db->query($sql);
        echo json_encode($query->result());
    }

    //保存用户参加的计划
    public function saveJoinedData()
    {
        $userName = $_POST['userName'];
        $planName = $_POST['planName'];
        $alarmWeek = $_POST['alarmWeek'];
        $pirvacySet = $_POST['pirvacySet'];
        $alarmTime = $_POST['alarmTime'];
        $isAlarm = $_POST['isAlarm'];

//        $userName = 'admin';
//        $planName = '123';
//        $alarmWeek = '1|2';
//        $pirvacySet = '1';
//        $alarmTime = '12|12';
//        $isAlarm = '0';

        //检查计划表中是否存在计划
        $planID = $this->checkPlanExist($planName);
        if ($planID == -1) {
            //插入计划
            $planID = $this->insertPlan($planName);
        }
        if ($planID != -1) {
            //检查用户是否参加该项目
            $joinID = $this->checkUserIsJoin($userName, $planID);
            if ($joinID == -1) {
                //插入用户参与记录
                $joinID = $this->insertJoinData($userName, $planID, $alarmWeek, $alarmTime, $pirvacySet, $isAlarm);
            } else {
                //更新用户参与数据
                if (!$this->updateJoinData($joinID, $alarmWeek, $alarmTime, $pirvacySet, $isAlarm)) {
                    $joinID = -1;
                }
            }
            echo $joinID;
        }
    }

    //更新用户参与计划的数据
    function updateJoinData($joinID, $alarmWeek, $alarmTime, $pirvacySet, $isAlarm)
    {
        $data = array('alarmWeek' => $alarmWeek, 'alarmTime' => $alarmTime,
            'privacySet' => $pirvacySet, 'isAlarm' => $isAlarm);
        $bool = $this->db->update('T_UserJoinPlan', $data, array('ID' => $joinID));
        return $bool;
    }

    //插入用户参与计划记录返回自自增ID,-1表示插入失败
    function insertJoinData($userName, $planID, $alarmWeek, $alarmTime, $pirvacySet, $isAlarm)
    {
        $data = array('userName' => $userName, 'planID' => $planID, 'alarmWeek' => $alarmWeek, 'alarmTime' => $alarmTime,
            'privacySet' => $pirvacySet, 'isAlarm' => $isAlarm);
        $bool = $this->db->insert('T_UserJoinPlan', $data);

        //项目参与人数自增一
        $sql = "update T_Plan set participation = participation+1 where planID = $planID";
        $this->db->query($sql);

        if ($bool) {
            return $this->db->insert_id();
        } else {
            return -1;
        }

    }

    //插入计划 返回自自增ID,-1表示插入失败
    function insertPlan($planName)
    {
        $data = array('planName' => $planName);
        $bool = $this->db->insert('T_Plan', $data);
        if ($bool) {
            return $this->db->insert_id();
        } else {
            return -1;
        }
    }

    //检查计划表中是否存在计划 返回planID, -1表示不存在
    function checkPlanExist($planName)
    {
        $sql = "select * from T_Plan where planName = '$planName'";
        $query = $this->db->query($sql);
        //echo $query->result()[0]->num;
        if (count($query->result()) >= 1) {
            return $query->result()[0]->planID;
        } else {
            return -1;
        }
    }

    //检查用户参与计划表中是否存在计划 返回ID, -1表示不存在
    function checkUserIsJoin($userName, $planID)
    {
        $sql = "select * from T_UserJoinPlan where planID = '$planID' and userName= '$userName'";
        $query = $this->db->query($sql);
        //echo $query->result()[0]->num;
        if (count($query->result()) >= 1) {
            return $query->result()[0]->ID;
        } else {
            return -1;
        }
    }

    //获取用户的签到信息
    function getSignData()
    {
        $userName = $_POST['userName'];
        //$userName = 'admin';
        $curDate = $_POST['curDate'];
        //$curDate = '2016-10-22';
        $sql = "select a.*,IFNULL(b.signNum,0) as signNum,IFNULL(c.lastSignDate,0) as lastSignDate from (T_Plan a " .
            "LEFT JOIN (select a.userName,a.planID,count(*) signNum from T_UserJoinPlan a,T_Sign b where a.userName = b.userName and a.planID = b.planID and a.userName='$userName' group by a.userName,a.planID) b ON a.planID = b.planID)" .
            "LEFT JOIN(select a.userName,a.planID,b.signDate as lastSignDate from T_UserJoinPlan a,T_Sign b where a.userName = b.userName and a.planID = b.planID and a.userName='$userName' and b.signDate = '$curDate') c ON a.planID = c.planID ,T_UserJoinPlan d " .
            "where a.planID = d.planID and d.userName = '$userName'";
        $query = $this->db->query($sql);
        echo json_encode($query->result());
    }

    //获得计划的签到信息
    function getSignByPlanID()
    {
        $userName = $_POST['userName'];
        //$userName = 'admin';
        $planID = $_POST['planID'];
        //$planID = '0';
        $sql = "select * from T_Sign where planID ='$planID' and userName='$userName'";
        $query = $this->db->query($sql);
        echo json_encode($query->result());
    }

    //删除用户参与的计划
    function deletePlan()
    {
        $userName = $_POST['userName'];
        $planID = $_POST['planID'];
//        $userName = 'admin';
//        $planID = 1;
        $bool = $this->db->delete('T_UserJoinPlan', array('planID' => $planID, 'userName' => $userName));
        $this->db->delete('T_Sign', array('planID' => $planID, 'userName' => $userName));
        $sql = "update T_Plan set participation = participation-1 where planID = $planID";
        $this->db->query($sql);
        if ($bool) {
            echo "请求成功";
        } else {
            echo "请求失败";
        }
    }

    //添加打卡记录
    function insertSign()
    {
        $userName = $_POST['userName'];
        $planID = $_POST['planID'];
        $signDate = $_POST['signDate'];
//        $userName = 'admin';
//        $planID = '1';
//        $signDate = '2016-10-22';
        $sql = "select * from T_Sign where userName='$userName' and planID='$planID' and signDate='$signDate'";
        $query = $this->db->query($sql);
        if (count($query->result()) > 0) {
            echo '已签到';
        } else {
            $bool = $this->db->insert("T_Sign", array('userName' => $userName, 'planID' => $planID, 'signDate' => $signDate));
            if ($bool) {
                echo '请求成功';
            } else {
                echo '请求失败';
            }
        }
    }

    function getHeadImageUrl()
    {
        $sql = "select * from T_CommunityHeadImage ";
        $query = $this->db->query($sql);
        echo json_encode($query->result());
    }


    function getCircleData()
    {
//        $userName = $_POST['userName'];
//        $hotNum = $_POST['hotNum'];
        $userName = 'admin';
        $hotNum = '0';
        $endNum = $hotNum + 10;
        $sql = "select a.* from T_CommunityCircle a where a.circleID not in (select b.circleID from T_JoinedCircle b where userName = '$userName') limit $hotNum,$endNum";
        $sql2 = "select a.* from T_CommunityCircle a,T_JoinedCircle b where a.circleID = b.circleID and userName = '$userName'";
        $query = $this->db->query($sql);
        $query2 = $this->db->query($sql2);

        echo json_encode($query->result()) . '-----' . json_encode($query2->result());
    }

    function quitCircle()
    {
        $userName = $_POST['userName'];
        $circleID = $_POST['circleID'];
        $bool = $this->db->delete("T_JoinedCircle", array('userName' => $userName, 'circleID' => $circleID));
        if ($bool) {
            echo '请求成功';
        } else {
            echo '请求失败';
        }
    }

    function addCircle()
    {
        $userName = $_POST['userName'];
        $circleID = $_POST['circleID'];
        $bool = $this->db->insert("T_JoinedCircle", array('userName' => $userName, 'circleID' => $circleID));
        if ($bool) {
            echo '请求成功';
        } else {
            echo '请求失败';
        }
    }

//    //删除
//    public function del(){
//        $bool=$this->db->delete('user',array('id'=>6));
//        if ($bool) {
//            echo "影响行数".$this->db->affected_rows();
//        }
//    }
    function getAllCircleData()
    {
        $sql = "select a.* from T_CommunityCircle a";
        $query = $this->db->query($sql);
        echo json_encode($query->result());
    }

    function getThemeByCircleID()
    {
        $circleID = $_POST['circleID'];
        $startNum = $_POST['startNum'];
//        $circleID = 0;
//        $startNum = 0;
        $endNum = $startNum + 10;
        $sql = "select a.*,b.nickName,b.headImgUrl,c.circleName from T_Theme a,T_User b,T_CommunityCircle c " .
            "where a.publishName=b.userName and a.circleID = c.circleID and a.circleID = $circleID limit $startNum,$endNum";
        $query = $this->db->query($sql);
        echo json_encode($query->result());
    }

    function getThemeData()
    {
        $startNum = $_POST['startNum'];
        //$startNum = 0;
        $endNum = $startNum + 10;
        $sql = "select a.*,b.nickName,b.headImgUrl,c.circleName from T_Theme a,T_User b,T_CommunityCircle c " .
            "where a.publishName=b.userName and a.circleID = c.circleID  limit $startNum,$endNum";
        $query = $this->db->query($sql);
        echo json_encode($query->result());
    }

    function getOtherCircleData(){
        $circleID = $_POST['circleID'];
        //$circleID = 0;
        $sql1 = "select count(*) as joinedCount from T_JoinedCircle where circleID ='$circleID'";
        $sql2 = "select count(*) as thmemCount from T_Theme where circleID ='$circleID'";
        $query1 = $this->db->query($sql1);
        $query2 = $this->db->query($sql2);
        echo $query1->result()[0]->joinedCount.'-----'.$query2->result()[0]->thmemCount;
    }

    function createTheme()
    {
        $publishName = $_POST['publishName'];
        $publishTime = $_POST['publishTime'];
        $themeTitle = $_POST['themeTitle'];
        $themeContent = $_POST['themeContent'];
        $circleID = $_POST['circleID'];
        $imgCount = $_POST['imgCount'];
       $array = array('themeTitle' => $themeTitle, 'themeContent' => $themeContent,
            'circleID' => $circleID, 'publishTime' => $publishTime, 'publishName' => $publishName);
        $filePath = $upload_path = getcwd(). '\resource\themeImg\\' . $publishName . '\A' . $themeTitle .
            $publishTime;
        $filePath=iconv("utf-8","gb2312",$filePath);
        if (!file_exists($filePath)) {
            mkdir($filePath,0777,true);
        }
        $config['upload_path'] = $filePath;
        $config['allowed_types'] = '*';
        $config['max_size'] = 1024;
        $config['max_width'] = 1024;
        $config['max_height'] = 768;
        $this->load->library('upload', $config);
        for ($i = 1; $i <= $imgCount; $i++) {
            //执行上传文件
            if (!$this->upload->do_upload('img' .$i)) {
                $error = array('error' => $this->upload->display_errors());
                echo '上传失败' . json_encode($error);
                return;
            }
            $array['img' . $i] = $this::$ROOT_RESOURCE . '\themeImg\\' . $publishName . '\A' . $themeTitle .
                $publishTime . '\img' . $i;
        }
        $bool = $this->db->insert('T_Theme', $array);
        if($bool){
            echo'请求成功';
        }else{
            echo'请求失败';
        }
    }


    function test()
    {
        $publishName = 'admin';
        $publishTime = '12312';
        $themeTitle = '123';
        //$themeContent = $_POST['themeContent'];
       // $circleID = $_POST['circleID'];
        //$imgCount = $_POST['imgCount'];
        $filePath = $upload_path = getcwd(). '\resource\themeImg\\' . $publishName . '\A' . $themeTitle .
            $publishTime;
        echo $filePath;
    }

//    //增加
//    public function insertdb(){
//        $data= array('name' => 'heha', 'pass'=>md5('1234'));
//        $bool=$this->db->insert('user',$data);
//        if ($bool) {
//            echo "影响行数".$this->db->affected_rows();
//            echo "自增id".$this->db->insert_id();
//        }
//    }

////更新
//    public function up(){
//        $data= array('name' => 'he', 'pass'=>md5('1234'));
//        $bool=$this->db->update('user',$data,array('id'=>3));
//        if($bool) {
//            echo "影响行数".$this->db->affected_rows();
//            echo "自增id".$this->db->insert_id();
//        }
//    }


}