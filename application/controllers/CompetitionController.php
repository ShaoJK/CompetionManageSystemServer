<?php

/**
 * Created by PhpStorm.
 * User: sjk
 * Date: 2016/11/26
 * Time: 22:21
 */
class CompetitionController extends CI_Controller
{
    static $ROOT_RESOURCE = 'http://10.0.2.2/CompetionManageSystem/resource';

    public function __construct()
    {
        parent::__construct();
        //连接数据库
        $this->load->database();
        $this->load->library('session');

    }


    /**
     * 获得所有的比赛信息列表
     */
    public function getCompetitionList()
    {
        $sql = "select compID,comp_type,comp_name,comp_time,state,s_time,f_time FROM t_competition";
        $result = $this->query($sql);
        $result = "{success:'true',msg:'请求成功',list:" . json_encode($result) . "}";
        echo $result;
    }

    public function getCompetitionDataDetailById()
    {
        $ID = $_POST['ID'];
        //  $ID = 1;
        $sql = "select a.*,b.r_name from t_competition a left join t_level b on a.level_id = b.rID " .
            "where a.compID = $ID";
        $result = $this->query($sql);
        if (count($result) > 0) {
            $major = $result[0]->major_limited;
            $major = $this->getLimitedMajor($major);
            $result = $this->setMsg('true', '操作成功', $result[0]);
            $prefix = '{ limitedMajor:' . json_encode($major) . ',';
            echo $prefix . substr($result, 1);
        } else {
            echo "{sucess:'false',msg:'比赛已结束'}";
        }

    }

    public function query($sql)
    {
        $query = $this->db->query($sql);
        return $query->result();
    }


    //获得限制的专业
    public function getLimitedMajor($major)
    {
        if (!empty($major)) {
            $major = str_replace('|', ",", $major);
            $major = '(' . $major . ')';
        } else {
            $major = '(-1)';
        }
        $sql = "select * from t_major where mID in $major";
        return $this->query($sql);
    }

    public function setMsg($sucess, $msg, $result)
    {
        $prefix = "{ success:'$sucess',msg:'$msg',";
        return $prefix . substr(json_encode($result), 1);
    }

    /**
     * 获得学院列表
     */
    public function getAcademyList()
    {
        $sql = "select * FROM t_academy";
        $result = $this->query($sql);
        $result = "{success:'true',msg:'请求成功',data:" . json_encode($result) . "}";
        echo $result;
    }


    /**
     * 获得系列表
     */
    public function getDepartmentList()
    {
        $academyID = $_POST['academyID'];
        $sql = "select * FROM t_department WHERE academy_id = $academyID";
        $result = $this->query($sql);
        $result = "{success:'true',msg:'请求成功',data:" . json_encode($result) . "}";
        echo $result;
    }


    /**
     * 获得老师列表
     */
    public function getTeacherList()
    {
        $departmentID = $_POST['departmentID'];
        $sql = "select * FROM t_teacher WHERE department_id = $departmentID";
        $result = $this->query($sql);
        $result = "{success:'true',msg:'请求成功',data:" . json_encode($result) . "}";
        echo $result;
    }

    /**
     * 单人比赛报名登记
     */
    public function applyRegister()
    {
        if (!$this->checkUserIfLogin()) {
            return;
        }

        $stuID = $_POST['stuID'];
        $competionID = $_POST['competionID'];
        $appTime = $_POST['appTime'];
        $teacher = $_POST['teacher'];
        $hasProof = $_POST['hasProof'];
//        $stuID = 111;
//        $competionID = 111;
//        $appTime = 1111;
//        $teacher = 1;
//        $hasProof = 0;
        $checkState = 1;
        $reason = '';

        //检查用户是否已经报名
        if ($this->checkUserIsJoinCompetition($stuID, $competionID)) {
            echo "{ success:'false',msg:'该学生已报名'}";
            return;
        }

        //上传文件
        $filePath = "";
        $fileName = "";
        if ($hasProof == 1) {
            $filePath = $upload_path = getcwd() . '\resource\proofImg\ID' . $stuID . '\A' . $appTime;
            $filePath = iconv("utf-8", "gb2312", $filePath);
            if (!file_exists($filePath)) {
                mkdir($filePath, 0777, true);
            }
            $config['upload_path'] = $filePath;
            $config['allowed_types'] = '*';
            $config['max_size'] = 1024;
            $config['max_width'] = 1024;
            $config['max_height'] = 768;
            $this->load->library('upload', $config);
            //执行上传文件
            if (!$this->upload->do_upload('proof')) {
                $error = array('error' => $this->upload->display_errors());
                $errorStr = json_encode($error);
                $result = "{success:'true',msg:'$errorStr'}";
                echo $result;
                return;
            }
            $filePath = $this::$ROOT_RESOURCE . '\proofImg\ID' . $stuID . '\A' . $appTime;
            $fileName = $this->upload->data('file_name');
        }


        //开启事物
        $this->db->trans_start();
        $array = array('stu_id' => $stuID, 'competition_id' => $competionID,
            'app_time' => $appTime, 'check_state' => $checkState, 'reason' => $reason,
            'proof' => $filePath . '\\' . $fileName);
        $this->db->insert('t_apply', $array);
        $apply_id = $this->db->insert_id("apply_id");
        if ($teacher != -1) {
            $array2 = array('apply_id' => $apply_id, 'teacher_id' => $teacher,
                'perpoint' => '1', 'isfirst' => '1');
            $this->db->insert('t_mentor', $array2);
        }
        $this->db->trans_complete();
        //事物提交
        if ($this->db->trans_status() === FALSE) {
            echo "{success:'false',msg:'请求失败'}";
        } else {
            echo "{success:'true',msg:'报名成功'}";
        }
    }

    /**
     * 检查用户是否已经报名参加了比赛
     */
    public function checkUserIsJoinCompetition($sutID, $competitionID)
    {
        if ($sutID == null) {
            return false;
        }
        $sql = "select * FROM t_apply where stu_id = $sutID and competition_id= $competitionID";
        $query = $this->db->query($sql);
        if (count($query->result()) > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 获得所有参加比赛的队伍的信息
     */
    public function getTeamInfoListByCompetionID()
    {
        $competitionID = $_POST['competitionID'];
        $sql = "SELECT a.groupID,a.competition_id competitionID,a.header_id captainID,f.s_name captainName,"
            . "b.group_num maxNum,e.ID teacherID, e.t_name teacherName,g.sum currentNum FROM t_group a " .
            "LEFT JOIN t_competition b ON a.competition_id = b.compID " .
            "LEFT JOIN t_apply c ON a.header_id = c.stu_id and c.competition_id = a.competition_id " .
            "LEFT JOIN t_mentor d ON d.apply_id = c.appID " .
            "LEFT JOIN t_teacher e ON e.ID = d.teacher_id " .
            "LEFT JOIN t_student f ON f.ID = a.header_id " .
            "LEFT JOIN (select group_id,count(*) sum from t_member GROUP BY group_id) g ON g.group_id = a.groupID " .
            "WHERE a.competition_id = $competitionID";

        $result = $this->query($sql);
        $result = "{success:'true',msg:'请求成功',data:" . json_encode($result) . "}";
        echo $result;
    }

    /**
     * 根据老师ID获得该老师所带的的队伍的信息
     */
    public function getTeamInfoListByCompetionIDAndTeacherID()
    {
        $competitionID = $_POST['competitionID'];
        $teacherID = $_POST['teacherID'];
        $captionName = $_POST['captainName'];
        $sql = "SELECT a.groupID,a.competition_id competitionID,a.header_id captainID,f.s_name captainName,"
            . "b.group_num maxNum,e.ID teacherID, e.t_name teacherName,g.sum currentNum FROM t_group a " .
            "LEFT JOIN t_competition b ON a.competition_id = b.compID " .
            "LEFT JOIN t_apply c ON a.header_id = c.stu_id and c.competition_id = a.competition_id " .
            "LEFT JOIN t_mentor d ON d.apply_id = c.appID " .
            "LEFT JOIN t_teacher e ON e.ID = d.teacher_id " .
            "LEFT JOIN t_student f ON f.ID = a.header_id " .
            "LEFT JOIN (select group_id,count(*) sum from t_member GROUP BY group_id) g ON g.group_id = a.groupID " .
            "WHERE a.competition_id = $competitionID and e.ID = $teacherID ";
        if ($captionName != '-1') {
            $sql = $sql . " and f.s_name = '$captionName'";
        }
        $result = $this->query($sql);
        $result = "{success:'true',msg:'请求成功',data:" . json_encode($result) . "}";
        echo $result;
    }

    /**
     * 创建一支队伍并报名参加比赛
     */
    public function groupApply()
    {

        if (!$this->checkUserIfLogin()) {
            return;
        }

        $stuID = $_POST['stuID'];
        $competionID = $_POST['competionID'];
        $appTime = $_POST['appTime'];
        $teacher = $_POST['teacher'];
        $hasProof = $_POST['hasProof'];
        $teamType = $_POST['teamType'];
        $teamID = $_POST['teamID'];

        $checkState = 1;
        $reason = '';

        //检查用户是否已经报名
        if ($this->checkUserIsJoinCompetition($stuID, $competionID)) {
            echo "{ success:'false',msg:'该学生已报名'}";
            return;
        }
//        echo "1111111111";
//        return;

        //上传文件
        $filePath = "";
        $fileName = "";
        if ($hasProof == 1) {
            $filePath = $upload_path = getcwd() . '\resource\proofImg\ID' . $stuID . '\A' . $appTime;
            $filePath = iconv("utf-8", "gb2312", $filePath);
            if (!file_exists($filePath)) {
                mkdir($filePath, 0777, true);
            }
            $config['upload_path'] = $filePath;
            $config['allowed_types'] = '*';
            $config['max_size'] = 1024;
            $config['max_width'] = 1024;
            $config['max_height'] = 768;
            $this->load->library('upload', $config);
            //执行上传文件
            if (!$this->upload->do_upload('proof')) {
                $error = array('error' => $this->upload->display_errors());
                $errorStr = json_encode($error);
                $result = "{success:'true',msg:'$errorStr'}";
                echo $result;
                return;
            }
            $filePath = $this::$ROOT_RESOURCE . '\proofImg\ID' . $stuID . '\A' . $appTime;
            $fileName = $this->upload->data('file_name');
        }

        //开启事物
        $this->db->trans_start();
        $array = array('stu_id' => $stuID, 'competition_id' => $competionID,
            'app_time' => $appTime, 'check_state' => $checkState, 'reason' => $reason,
            'proof' => $filePath . '\\' . $fileName);
        $this->db->insert('t_apply', $array);
        $apply_id = $this->db->insert_id("apply_id");
        //如果是新建队伍报名，则插入队伍,且插入指导老师信息
        if ($teamType == 0x11) {
            $groupParams = array('competition_id' => $competionID, 'header_id' => $stuID, 'g_state' => 1);
            $this->db->insert('t_group', $groupParams);
            $teamID = $this->db->insert_id("t_group");
            if ($teacher != -1) {//插入指导老师信息
                $array2 = array('apply_id' => $apply_id, 'teacher_id' => $teacher,
                    'perpoint' => '1', 'isfirst' => '1');
                $this->db->insert('t_mentor', $array2);
            }
        }
        //将信息插入队员表
        $array3 = array('stu_id' => $stuID, 'group_id' => $teamID);
        $this->db->insert('t_member', $array3);
        $this->db->trans_complete();
        //事物提交
        if ($this->db->trans_status() === FALSE) {
            echo "{success:'false',msg:'请求失败'}";
        } else {
            echo "{success:'true',msg:'报名成功'}";
        }
    }

    /**
     * 根据队长姓名进行查找
     */
    public function selectTeamByName()
    {
        $competitionID = $_POST['competitionID'];
        $captionName = $_POST['captainName'];
        $sql = "SELECT a.groupID,a.competition_id competitionID,a.header_id captainID,f.s_name captainName,"
            . "b.group_num maxNum,e.ID teacherID, e.t_name teacherName,g.sum currentNum FROM t_group a " .
            "LEFT JOIN t_competition b ON a.competition_id = b.compID " .
            "LEFT JOIN t_apply c ON a.header_id = c.stu_id and c.competition_id = a.competition_id " .
            "LEFT JOIN t_mentor d ON d.apply_id = c.appID " .
            "LEFT JOIN t_teacher e ON e.ID = d.teacher_id " .
            "LEFT JOIN t_student f ON f.ID = a.header_id " .
            "LEFT JOIN (select group_id,count(*) sum from t_member GROUP BY group_id) g ON g.group_id = a.groupID " .
            "WHERE a.competition_id = $competitionID and f.s_name = $captionName";

        $result = $this->query($sql);
        $result = "{success:'true',msg:'请求成功',data:" . json_encode($result) . "}";
        echo $result;
    }

    /**
     * 检查用户是否登录
     */
    public function checkUserIfLogin()
    {
        $id = $this->session->userdata('id');
        if ($id == null) {
            echo "{ success:'false',msg:'未登录'}";
            return false;
        } else {
            return true;
        }
    }


    /**
     * 老师对进行个人赛代报名
     */
    public function appolySingleCompByTeacher()
    {
        if (!$this->checkUserIfLogin()) {
            return;
        }
        $stuNumber = $_POST['stuNumber'];
        $competionID = $_POST['competionID'];
        $appTime = $_POST['appTime'];
        $teacher = $_POST['teacher'];
        $hasProof = $_POST['hasProof'];

        //对学生的正确性进行校验
        $stuID = $this->checkUserNumber($stuNumber);
        if ($stuID == -1) {
            echo "{success:'false',msg:'学生学号输入错误'}";
            return;
        }

        $checkState = 1;
        $reason = '';

        //判断学生符不符合报名条件
        if (!$this->checkUserIsCanApply($stuID, $competionID)) {
            return;
        }

        //上传文件
        $filePath = "";
        $fileName = "";
        if ($hasProof == 1) {
            $filePath = $upload_path = getcwd() . '\resource\proofImg\ID' . $stuID . '\A' . $appTime;
            $filePath = iconv("utf-8", "gb2312", $filePath);
            if (!file_exists($filePath)) {
                mkdir($filePath, 0777, true);
            }
            $config['upload_path'] = $filePath;
            $config['allowed_types'] = '*';
            $config['max_size'] = 1024;
            $config['max_width'] = 1024;
            $config['max_height'] = 768;
            $this->load->library('upload', $config);
            //执行上传文件
            if (!$this->upload->do_upload('proof')) {
                $error = array('error' => $this->upload->display_errors());
                $errorStr = json_encode($error);
                $result = "{success:'true',msg:'$errorStr'}";
                echo $result;
                return;
            }
            $filePath = $this::$ROOT_RESOURCE . '\proofImg\ID' . $stuID . '\A' . $appTime;
            $fileName = $this->upload->data('file_name');
        }

        //开启事物
        $this->db->trans_start();
        $array = array('stu_id' => $stuID, 'competition_id' => $competionID,
            'app_time' => $appTime, 'check_state' => $checkState, 'reason' => $reason,
            'proof' => $filePath . '\\' . $fileName);
        $this->db->insert('t_apply', $array);
        $apply_id = $this->db->insert_id("apply_id");
        if ($teacher != -1) {
            $array2 = array('apply_id' => $apply_id, 'teacher_id' => $teacher,
                'perpoint' => '1', 'isfirst' => '1');
            $this->db->insert('t_mentor', $array2);
        }
        $this->db->trans_complete();
        //事物提交
        if ($this->db->trans_status() === FALSE) {
            echo "{success:'false',msg:'请求失败'}";
        } else {
            echo "{success:'true',msg:'报名成功'}";
        }

    }


    /**
     *  验证学生学号是否正确
     * 正确则放回ID，错误返回-1
     */
    public function checkUserNumber($number)
    {
        $sql = "select ID from t_student where s_number = $number";
        $result = $this->query($sql);
        if (count($result) <= 0) {
            return -1;
        } else {
            return $result[0]->ID;
        }
    }


    /**
     * 获取学生信息
     */
    public function getStudentInfo($stuID)
    {
        $sql = "select a.ID,a.s_number,a.pwd,a.s_name,a.sex,a.study_status,a.phone,a.email,a.IDcard," .
            "b.cID,b.c_name,d.dID,d.d_name,c.mID,c.m_name,e.aID,e.a_name,b.grade " .
            "from t_student a,t_class b,t_major c,t_department d,t_academy e " .
            "where a.class_id = b.cID and b.major_id = c.mID " .
            "and c.department_id = d.dID and d.academy_id = e.aID and a.ID=$stuID";
        $result = $this->query($sql);
        if (count($result) > 0) {
            return $result[0];
        } else {
            return null;
        }
    }

    /**
     * 判断学生符不符合报名条件
     */
    public function checkUserIsCanApply($stuID, $competionID)
    {
        $majorLimit = false;//专业是否符合
        $gradeLimit = false;//年级是否符合

        $stuInfo = $this->getStudentInfo($stuID);
        if ($stuInfo == null) {
            echo "{success:'false',msg:'学生信息错误'}";
            return false;
        }

        //检查用户是否已经报名
        if ($this->checkUserIsJoinCompetition($stuID, $competionID)) {
            echo "{ success:'false',msg:'该学生已报名'}";
            return false;
        }

        //获取比赛的信息
        $sql = "select a.*,b.r_name from t_competition a left join t_level b on a.level_id = b.rID " .
            "where a.compID = $competionID";
        $competitionInfo = $this->query($sql);
        if (count($competitionInfo) <= 0) {
            echo "{success:'false',msg:'竞赛信息错误'}";
        } else {
            //获取限制的专业信息
            $competitionInfo = $competitionInfo[0];
            $majors = $competitionInfo->major_limited;
            $majors = $this->getLimitedMajor($majors);
        }

        //对学生的专业进行判断
        if (count($majors) <= 0) {//无专业限制
            $majorLimit = true;
        } else {
            foreach ($majors as $major) {
                if ($major->mID == $stuInfo->mID) {
                    $majorLimit = true;
                    break;
                }
            }
        }

        //对学生年级进行判断
        if ($competitionInfo->grade_limited == null ||
            empty($competitionInfo->grade_limited) ||
            $stuInfo->grade == null ||
            strpos($competitionInfo->grade_limited, $stuInfo->grade)
        ) {
            $gradeLimit = true;
        }

        if (!$majorLimit) {
            echo "{success:'false',msg:'专业不符合要求'}";
            return false;
        } else if (!$gradeLimit) {
            echo "{success:'false',msg:'年级不符合要求'}";
            return false;
        } else {
            return true;
        }

    }


    /**
     *  组队赛老师代报名
     */
    public function groupApplyByTeacher()
    {
        if (!$this->checkUserIfLogin()) {
            return;
        }
        $stuNumber = $_POST['stuNumber'];
        $competionID = $_POST['competionID'];
        $appTime = $_POST['appTime'];
        $teacher = $_POST['teacher'];
        $teamID = $_POST['teamID'];
        $hasProof = $_POST['hasProof'];
        $checkState = 1;
        $reason = '';

        //对学生的正确性进行校验
        $stuID = $this->checkUserNumber($stuNumber);
        if ($stuID == -1) {
            echo "{success:'false',msg:'学生学号输入错误'}";
            return;
        }
        //判断学生符不符合报名条件
        if (!$this->checkUserIsCanApply($stuID, $competionID)) {
            return;
        }
        //上传文件
        $filePath = "";
        $fileName = "";
        if ($hasProof == 1) {
            $filePath = $upload_path = getcwd() . '\resource\proofImg\ID' . $stuID . '\A' . $appTime;
            $filePath = iconv("utf-8", "gb2312", $filePath);
            if (!file_exists($filePath)) {
                mkdir($filePath, 0777, true);
            }
            $config['upload_path'] = $filePath;
            $config['allowed_types'] = '*';
            $config['max_size'] = 1024;
            $config['max_width'] = 1024;
            $config['max_height'] = 768;
            $this->load->library('upload', $config);
            //执行上传文件
            if (!$this->upload->do_upload('proof')) {
                $error = array('error' => $this->upload->display_errors());
                $errorStr = json_encode($error);
                $result = "{success:'true',msg:'$errorStr'}";
                echo $result;
                return;
            }
            $filePath = $this::$ROOT_RESOURCE . '\proofImg\ID' . $stuID . '\A' . $appTime;
            $fileName = $this->upload->data('file_name');
        }

        //开启事物
        $this->db->trans_start();
        $array = array('stu_id' => $stuID, 'competition_id' => $competionID,
            'app_time' => $appTime, 'check_state' => $checkState, 'reason' => $reason,
            'proof' => $filePath . '\\' . $fileName);
        $this->db->insert('t_apply', $array);
        $apply_id = $this->db->insert_id("apply_id");
        //如果是新建队伍报名，则插入队伍,且插入指导老师信息
        if ($teamID == -1) {
            $groupParams = array('competition_id' => $competionID, 'header_id' => $stuID, 'g_state' => 1);
            $this->db->insert('t_group', $groupParams);
            $teamID = $this->db->insert_id("t_group");
            if ($teacher != -1) {//插入指导老师信息
                $array2 = array('apply_id' => $apply_id, 'teacher_id' => $teacher,
                    'perpoint' => '1', 'isfirst' => '1');
                $this->db->insert('t_mentor', $array2);
            }
        }
        //将信息插入队员表
        $array3 = array('stu_id' => $stuID, 'group_id' => $teamID);
        $this->db->insert('t_member', $array3);
        $this->db->trans_complete();
        //事物提交
        if ($this->db->trans_status() === FALSE) {
            echo "{success:'false',msg:'请求失败'}";
        } else {
            $stuInfo = $this->getStudentInfo($stuID);
            $prefix = "{ success:'true',msg:'$teamID',";
            echo $prefix . substr(json_encode($stuInfo), 1);
        }
    }

    /**
     * 团体赛取消报名
     */
    public function cancleGroupCompApply()
    {
        if (!$this->checkUserIfLogin()) {
            return;
        }
        $stuID = $_POST['stuID'];
        $competionID = $_POST['competionID'];
        $teamID = $_POST['teamID'];
        $isCapatain = $_POST['isCapation'];
        //开启事物
        $this->db->db_debug = FALSE;
        $this->db->trans_start();

        $querySql = "select appID from t_apply where stu_id = $stuID and competition_id= $competionID";
        $result = $this->query($querySql);
        if (count($result) <= 0) {
            echo "{success:'false',msg:'报名信息错误'}";
            return;
        }
        $applyID = $result[0]->appID;
        $applyArray = array('stu_id' => $stuID, 'competition_id' => $competionID);
        $this->db->delete('t_apply', $applyArray);
        if ($isCapatain == 1) {//是队长
            $groupArray = array('groupID' => $teamID, 'competition_id' => $competionID, 'header_id' => $stuID);
            $this->db->delete('t_group', $groupArray);
            $this->db->delete('t_mentor', array('apply_id' => $applyID));
        }

        $memberArray = array('group_id' => $teamID, 'stu_id' => $stuID);
        $this->db->delete('t_member', $memberArray);
        $this->db->trans_complete();
        //事物提交
        if ($this->db->trans_status() === FALSE) {
            echo "{success:'false',msg:'请求失败'}";
        } else {
            echo "{success:'true',msg:'请求成功'}";
        }
    }


    /**
     * 获得团队里里面所有学生的信息
     */
    public function getAllTeamInfoByGroupID()
    {
        $groupID = $_POST['teamID'];

        $this->db->trans_start();
        $sql1 = "select * from t_student WHERE id in (SELECT stu_id from t_member where group_id = $groupID)";
        $stuInfo = $this->query($sql1);
        $sql2 = "select header_id from t_group where groupID = $groupID";
        $headerInfo = $this->query($sql2);
        if (count($headerInfo) <= 0 || count($stuInfo) <= 0) {
            echo "{success:'false',msg:'团队信息异常'}";
            return;
        }
        $this->db->trans_complete();
        if ($this->db->trans_status() === FALSE) {
            echo "{success:'false',msg:'请求失败'}";
        } else {
            echo "{success:'true',msg:'请求成功', data:" .
                json_encode($stuInfo) . ",capationID:'" . $headerInfo[0]->header_id . "'}";
        }
    }


    /**
     * 获得学生的所参加的比赛信息
     */
    public function getStudentCompetitionListById()
    {
        if (!$this->checkUserIfLogin()) {
            return;
        }

        $id = $this->session->userdata('id');
        $sql = "select a.compID,a.comp_type,a.comp_name,a.comp_time,a.state,a.s_time,a.f_time," .
            "b.stu_id stuID,c.s_name stuName,c.s_number stuNum " .
            "FROM t_competition a,t_apply b,t_student c where b.competition_id = a.compID" .
            " and b.stu_id = $id  and c.ID = b.stu_id";
        $result = $this->query($sql);
        $result = "{success:'true',msg:'请求成功',list:" . json_encode($result) . "}";
        echo $result;
    }

    /**
     * 获得老师的所参加的比赛信息
     */
    public function getTeacherCompetitionListById()
    {
        if (!$this->checkUserIfLogin()) {
            return;
        }

        $id = $this->session->userdata('id');
        $sql = "SELECT a.compID, a.comp_type, a.comp_name,a.comp_time,a.state,a.s_time,a.f_time," .
            "b.stu_id stuID,d.s_name stuName,d.s_number stuNum  " .
            "FROM t_competition a,t_apply b,t_mentor c ,t_student d WHERE c.apply_id = b.appID " .
            "AND b.competition_id = a.compID AND c.teacher_id = $id and d.ID = b.stu_id";
        $result = $this->query($sql);
        $result = "{success:'true',msg:'请求成功',list:" . json_encode($result) . "}";
        echo $result;
    }

    /**
     * 报名取消
     */
    public function cancleCompetitionApply()
    {
        $stuID = $_POST['stuID'];
        $competitionID = $_POST['competitionID'];

        if (!$this->checkUserIfLogin()) {
            return;
        }

        $this->cancleApply($competitionID, $stuID);
    }

    /**
     * 获得队伍的ID
     */
    public function getTeamID()
    {
        $stuID = $_POST['stuID'];
        $competitionID = $_POST['competitionID'];

        if (!$this->checkUserIfLogin()) {
            return;
        }
        //获得groupID
        $groupResult = $this->query("select b.groupID from t_group b where b.competition_id = $competitionID and b.groupID in (select c.group_id from t_member c where c.stu_id = $stuID)");
        if (count($groupResult) <= 0) {
            echo "{ success:'false',msg:'队伍信息错误'}";
            return;
        }
        $groupID = $groupResult[0]->groupID;
        echo "{ success:'true',msg:'$groupID'}";
    }

    /**
     * 通用取消报名
     */
    public function cancleApply($competitionID, $stuID)
    {
        //获得的比赛的类型
        $typeResult = $this->query("select comp_type from t_competition where compID = $competitionID");
        if (count($typeResult) <= 0) {
            echo "{ success:'false',msg:'竞赛信息错误'}";
            return;
        }

        //获得appID
        $appIDResult = $this->query("select appID from t_apply where stu_id = $stuID and competition_id = $competitionID");
        if (count($appIDResult) <= 0) {
            echo "{ success:'false',msg:'报名信息错误 select appID from t_apply where stu_id = $stuID and competition_id = $competitionID'}";
            return;
        }
        $appID = $appIDResult[0]->appID;
        $type = $typeResult[0]->comp_type;

        $this->db->db_debug = FALSE;
        $this->db->trans_start();
        if ($type == 1) {//个人赛
            $applyArray = array('stu_id' => $stuID, 'competition_id' => $competitionID);
            $this->db->delete('t_apply', $applyArray);
            $this->db->delete('t_mentor', array('apply_id' => $appID));
        } else {//组队赛
            //判断stu是否是队长
            $groupResult = $this->query("select * from t_group where competition_id = $competitionID and header_id = $stuID");

            if (count($groupResult) <= 0) {//不是队长
                $applyArray = array('stu_id' => $stuID, 'competition_id' => $competitionID);
                $this->db->delete('t_apply', $applyArray);
                //获得groupID
                $groupResult = $this->query("select b.groupID from t_group b where b.competition_id = $competitionID and b.groupID in (select c.group_id from t_member c where c.stu_id = $stuID)");
                if (count($groupResult) <= 0) {
                    echo "{ success:'false',msg:'队伍信息错误'}";
                    return;
                }
                $groupID = $groupResult[0]->groupID;
                $memberArray = array('stu_id' => $stuID, 'group_id' => $groupID);
                $this->db->delete('t_member', $memberArray);
            } else {//队长
                $this->db->delete('t_mentor', array('apply_id' => $appID));
                $groupResult = $this->query("select b.groupID from t_group b where b.competition_id = $competitionID and header_id = $stuID");

                if (count($groupResult) <= 0) {
                    echo "{ success:'false',msg:'队伍信息错误'}";
                    return;
                }
                $groupID = $groupResult[0]->groupID;

                $sql = "delete from t_apply where stu_id in (select stu_id from t_member where group_id = $groupID ) and competition_id = $competitionID";
                $query = $this->db->query($sql);
                $this->db->delete('t_member', array('group_id' => $groupID));
                $this->db->delete('t_group', array('groupID' => $groupID));
            }
        }
        //事物提交
        $this->db->trans_complete();
        if ($this->db->trans_status() === FALSE) {
            echo "{success:'false',msg:'请求失败'}";
        } else {
            echo "{success:'true',msg:'请求成功'}";
        }
    }

    /**
     * 学生获取所获奖项列表
     */
    public function getAwardList()
    {
        $stuID = $_POST["stuID"];

        if (!$this->checkUserIfLogin()) {
            return;
        }
        $sql = "select c.*,d.s_name stuName,s_number stuNumber,e.compID compID," .
            "e.comp_name compName,e.comp_type compType,f.r_name " .
            "from( SELECT a.stu_id,a.competition_id,b.* FROM t_apply a,t_contestants b " .
            "WHERE a.appID = b.apply_id AND a.stu_id = $stuID ) c " .
            "LEFT JOIN t_student d on d.ID = c.stu_id " .
            "LEFT JOIN t_competition e on c.competition_id = e.compID " .
            "LEFT JOIN t_level f on c.level_id = f.rID ";

        $result = $this->query($sql);
        $result = "{success:'true',msg:'请求成功',data:" . json_encode($result) . "}";
        echo $result;
    }

    /**
     * 老师获取所获奖项列表
     */
    public function getAwardListByTeacher()
    {
        $teacher = $_POST["teacID"];

        if (!$this->checkUserIfLogin()) {
            return;
        }
        $sql = "select c.*,d.s_name stuName,s_number stuNumber,e.compID compID," .
            "e.comp_name compName,e.comp_type compType,f.r_name " .
            "from( SELECT a.stu_id,a.competition_id,b.*,g.perpoint teachPoint FROM t_apply a,t_contestants b,t_mentor g " .
            "WHERE a.appID = b.apply_id and g.apply_id =b.apply_id  AND g.teacher_id = $teacher ) c  " .
            "LEFT JOIN t_student d on d.ID = c.stu_id " .
            "LEFT JOIN t_competition e on c.competition_id = e.compID " .
            "LEFT JOIN t_level f on c.level_id = f.rID ";

        $result = $this->query($sql);
        $result = "{success:'true',msg:'请求成功',data:" . json_encode($result) . "}";
        echo $result;
    }


}

