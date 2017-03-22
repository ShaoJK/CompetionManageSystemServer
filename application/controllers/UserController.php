<?php

/**
 * Created by PhpStorm.
 * User: sjk
 * Date: 2016/11/26
 * Time: 22:21
 */
class UserController extends CI_Controller
{
    public function __construct()
    {
        parent::__construct();
        //连接数据库
        $this->load->database();
        $this->load->library('session');
    }

    public function login()
    {
        $loginType = $_POST['type'];
        $userName = $_POST['username'];
        $userPwd = $_POST['password'];
//        $loginType = 1;
//        $userName = '13110033103';
//        $userPwd = '123456';
        if ($loginType == 1) {
            $sql = "select a.ID,a.s_number,a.pwd,a.s_name,a.sex,a.study_status,a.phone,a.email,a.IDcard," .
                "b.cID,b.c_name,d.dID,d.d_name,c.mID,c.m_name,e.aID,e.a_name,b.grade " .
                "from t_student a,t_class b,t_major c,t_department d,t_academy e " .
                "where a.class_id = b.cID and b.major_id = c.mID " .
                "and c.department_id = d.dID and d.academy_id = e.aID and a.s_number = '$userName' and a.pwd = '$userPwd'";
        } else {
            $sql = "select a.ID,a.pwd,a.t_number,a.t_name,a.sex,a.phone,a.email,a.onjob, " .
                " b.aID,b.a_name,c.mID,c.m_name,d.dID,d.d_name " .
                "from t_teacher a " .
                "LEFT JOIN t_academy b on a.academy_id = b.aID " .
                "LEFT JOIN t_major c on a.major_id = c.mID " .
                "LEFT JOIN t_department d on a.department_id = d.dID " .
                "where a.t_number = '$userName' and a.pwd = '$userPwd'";
        }
        $query = $this->db->query($sql);
        if (count($query->result()) > 0) {
            $this->savaInfoInSession($loginType,$query->result()[0]->ID,$userName);
            $prefix = "{ success:'true',msg:'登录成功',";
            echo $prefix . substr(json_encode($query->result()[0]), 1);
        } else {
            $result = "{success:'false',msg:'用户名或密码错误'}";
            echo $result;
        }
    }

    public function updateStudent()
    {
        $ID = $_POST['ID'];
        $pwd = $_POST['pwd'];
        $sex = $_POST['sex'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];
        $IDcard = $_POST['IDcard'];

        $data = array('pwd' => $pwd, 'sex' => $sex,
            'phone' => $phone, 'email' => $email, 'IDcard' => $IDcard);
        $bool = $this->db->update('t_student', $data, array('ID' => $ID));
        if ($bool) {
            $result = "{success:'true',msg:'修改成功'}";
        } else {
            $result = "{success:'false',msg:'修改失败'}";
        }
        echo $result;
    }

    public function updateTeacher()
    {
        $ID = $_POST['ID'];
        $pwd = $_POST['pwd'];
        $sex = $_POST['sex'];
        $phone = $_POST['phone'];
        $email = $_POST['email'];

        $data = array('pwd' => $pwd, 'sex' => $sex,
            'phone' => $phone, 'email' => $email);
        $bool = $this->db->update('t_teacher', $data, array('ID' => $ID));
        if ($bool) {
            $result = "{success:'true',msg:'修改成功'}";
        } else {
            $result = "{success:'false',msg:'修改失败'}";
        }
        echo $result;
    }

    public function changeUserPwd()
    {
        $type = $_POST['type'];//0x01 student 0x02 teacher
        $number = $_POST['userNumber'];//用户学工号
        $odlPwd = $_POST['oldPwd'];//原密码
        $newPwd = $_POST['newPwd'];//新密码

//        $type = 0x01;//0x01 student 0x02 teacher
//        $number =13110043236;//用户学工号
//        $odlPwd = 123456;//原密码
//        $newPwd = 111111;//新密码

        $result = "{success:'false',msg:'网络异常'}";
        switch ($type) {
            case 0x01:
                $sql = "select * from t_student where s_number='$number' and pwd = '$odlPwd'";
                $query = $this->db->query($sql);
                if (!$this->isHasData($query)) {
                    $result = "{success:'false',msg:'密码错误'}";
                } else {
                    $data = array('pwd' => $newPwd);
                    $bool = $this->db->update('t_student', $data, array('s_number' => $number));
                    if ($bool) {
                        $result = "{success:'true',msg:'修改成功'}";
                    } else {
                        $result = "{success:'false',msg:'网络异常'}";
                    }
                }
                break;
            case 0x02:
                $sql = "select * from t_teacher where t_number='$number' and pwd = '$odlPwd'";
                $query = $this->db->query($sql);
                if (!$this->isHasData($query)) {
                    $result = "{success:'false',msg:'密码错误'}";
                } else {
                    $data = array('pwd' => $newPwd);
                    $bool = $this->db->update('t_teacher', $data, array('t_number' => $number));
                    if ($bool) {
                        $result = "{success:'true',msg:'修改成功'}";
                    } else {
                        $result = "{success:'false',msg:'网络异常'}";
                    }
                    break;
                }
        }

        echo $result;
    }

    public function isHasData($query)
    {
        if (count($query->result()) > 0) {
            return true;
        } else {
            return false;
        }
    }

    /**
     * 将用户信息储存在session中
     */
    public function savaInfoInSession($type,$id,$userNum){
        $_SESSION['type'] = $type;
        $_SESSION['id']=$id;
        $_SESSION['userNum']=$userNum;
    }
}