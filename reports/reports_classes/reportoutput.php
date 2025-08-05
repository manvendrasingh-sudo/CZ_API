<?php
if(file_exists("/var/www/html/base_path.php")){
    include_once("/var/www/html/base_path.php");
}

interface OutputInterface
{
    public function jsonStringOutput();
    public function arrayOutput();
}

class Reportoutput extends DBconnection implements OutputInterface
{
    private $reportArr;
    private $finalreports = array();
    private $mysqlObj;
    private $conn;
    private $finalquery;
    private $reportObj;
    private $report_name;
    private $initial = 0;
    private $reportquery;
    private $current_month;
    private $campArr            = array();
    private $listArr            = array();
    private $skillArr           = array();
    private $agentArr           = array();
    private $custDispositionArr = array();
    private $dialerTypeArr      = array("ALL", "PREDICTIVE", "PREVIEW", "PROGRESSIVE");
    private $campaignTypeArr    = array("ALL", "INBOUND", "OUTBOUND");
    private $campaign_id;
    private $campaign_type;
    private $dwnl_reports_flag;
    private $CZ_DWLD_DATA_DIR = "/var/www/html/dwld_report_path";
    private $report_user_name;
    private $table_name;
    private $misscall_table_name;
    private $agent_table_name;
    private $fieldHeadings;
    private $filterval;
    private $reg_min_timestamp;
    private $reg_max_timestamp;
    private $campaignBetween;
    private $agentBetween;
    private $filerObj;
    public function __construct($db)
    {
        $this->conn = $db->mysqlConnection($config);
        //$this->campArr=$this->fetchCampaignArr();
    }

    public function setReportFields($reportObj, $config, $filerObj, $report_name, $current_month, $dwnl_reports_flag, $report_user_name,$table_name, $page = 1, $limit = 1000)
    {
        $this->dwnl_reports_flag = $dwnl_reports_flag;
        $this->reportArr         = $config->reportArr();
        $this->report_name       = $report_name;
        $this->report_user_name  = $report_user_name;
        $this->campaign_id       = $filerObj->getCampaignId();
        $this->campaign_type     = $filerObj->getCampaignType();

        if ($this->campaign_type) {
            $type    = explode("=", $this->campaign_type);
            $type[1] = trim($type[1]);

            if (substr($type[1], -3, 3) == "and") {
                $camp_type = substr($type[1], 0, -3);
            }
            $camp_type           = str_replace("'", "", $camp_type);
            $this->campaign_type = trim($camp_type);
        }

        $this->filerObj = $filerObj;

        if ($this->report_name === 'agent_session_report' || $this->report_name === "agent_login_report"){
            $this->filterval = $filerObj->getBetweenDates();
        } else {
            $this->filterval = $filerObj->getFilterCondition();
        }

        $this->table_name=$table_name;
        $this->misscall_table_name = "misscall_" . $this->table_name;
        $this->agent_table_name    = "agent_state_analysis_" . $this->table_name;
        $strmintimestamp           = $filerObj->getStartDate() . "  " . $filerObj->getStartTime();
        $strmaxtimestamp           = $filerObj->getEndDate() . "  " . $filerObj->getEndTime();
        $this->reg_min_timestamp   = strtotime($strmintimestamp);
        $this->reg_max_timestamp   = strtotime($strmaxtimestamp);
        $this->campaignBetween     = $filerObj->getCampaignBetween();
        $this->agentBetween        = $filerObj->getAgentBetween();
        $this->agent_id            = ($filerObj->getAgentId()) ? $filerObj->getAgentId() : "";
        $query                     = $this->finalReport();
        eval("\$query = \"$query\";");
        if (!preg_match('/There are no/', $query)) {
            return ($this->dwnl_reports_flag) ? $this->downloadReportIntoCSV($query) : $this->fetchQueryRecords($query, $page, $limit);
        } else {
        file_put_contents("$base_path/report_api_integration.txt","Inside Else2\n",FILE_APPEND);
            return $query;
        }

    }

    public function jsonStringOutput()
    {
        return json_encode($this->finalreports);
    }

    public function arrayOutput()
    {
        return $this->finalreports;
    }

    public function finalReport()
    {
        $query = $this->reportArr[$this->report_name]['query'];
        eval("\$query = \"$query\";");
        if ($this->report_name == "agent_break_report") {
            $query = $this->agentBreakReport("query");

            return $query;
        } else if ($query == "czentrix_agent_summary_report") {

            $reg_min_timestamp = $this->reg_min_timestamp;
            $reg_max_timestamp = $this->reg_max_timestamp;
            $agent_id          = $this->agent_id;
            $agent_id          = filter_var($agent_id, FILTER_SANITIZE_NUMBER_INT);
            $campaign_id       = filter_var($this->campaign_id, FILTER_SANITIZE_NUMBER_INT);
            $campaignBetween   = $this->campaignBetween;
            if (substr($this->agent_id, -3, 3) == "and") {
                $this->agent_id = " and " . substr($this->agent_id, 0, -3);
            }

            if (substr($this->campaign_id, -3, 3) == "and") {
                $this->campaign_id = " and " . substr($this->campaign_id, 0, -3);
            }

            $group_by = ($this->report_name == "campaign_call_summary") ? $this->reportArr[$this->report_name]['group by'][$this->campaign_type] : $this->reportArr[$this->report_name]['group by'];
            eval("\$group_by = \"$group_by\";");
            $group_by = rtrim($group_by, "and");

            $query    = "select c.agent_name,c.agent_id,c.campaign_name,ifnull(d.total_call,0),ifnull(d.answd_prev,0),ifnull(d.answd_pro,0),ifnull(d.answd_pred,0),ifnull(d.disp_set,0),ifnull(d.disp_not_set,0),ifnull(d.calls_held,0),ifnull(HOUR_MINUTES(d.Acdtime),'00:00:00') as talk_time,ifnull(HOUR_MINUTES(d.Acdtime/d.total_call),'00:00:00') as avg_talk_time,ifnull(HOUR_MINUTES(d.preview_talk_time),'00:00:00'),ifnull(HOUR_MINUTES(if(d.ring_time!='0',(if((d.Acdtime -(d.ring_time + d.hold_time)) > 0,(d.Acdtime -(d.ring_time + d.hold_time)),(d.Acdtime - d.hold_time))),(d.Acdtime - d.hold_time))),'00:00:00') as actual_talk_time,ifnull(HOUR_MINUTES(d.ring_time),'00:00:00'),ifnull(HOUR_MINUTES(d.ring_time/d.total_call),'00:00:00') as avg_ring_time,ifnull(HOUR_MINUTES(d.hold_time),'00:00:00'),ifnull(HOUR_MINUTES(d.hold_time/d.total_call),'00:00:00') as avg_hold_time,ifnull(HOUR_MINUTES(d.total_call_duration),'00:00:00') as call_handled_time,ifnull(HOUR_MINUTES(d.total_call_duration/d.total_call),'00:00:00') as avg_call_handled_duration,ifnull(d.wrap_count,0),ifnull(HOUR_MINUTES(d.Acwtime),'00:00:00') as wrapup_time,ifnull(HOUR_MINUTES(d.Acwtime/d.total_call),'00:00:00') as avg_wrapup_time,ifnull(HOUR_MINUTES(c.total_login_time),'00:00:00'),ifnull(HOUR_MINUTES(c.total_login_time - ifnull(d.total_call_duration,0) - c.pauseDuration - c.conf_duration),'00:00:00') as idle_time,ifnull(HOUR_MINUTES(c.pauseDuration),'00:00:00'),ifnull(HOUR_MINUTES(c.preview_pause_duration),'00:00:00'),ifnull(HOUR_MINUTES(c.ready_time),'00:00:00'),ifnull(HOUR_MINUTES(c.total_login_time-c.pauseDuration-c.ready_time),'00:00:00') as staff_time,ifnull(HOUR_MINUTES(c.conf_duration),'00:00:00'),ifnull(HOUR_MINUTES(c.total_preview_time),'00:00:00'),ifnull(HOUR_MINUTES(if(c.total_preview_time - ifnull(d.preview_talk_time,0) - c.preview_pause_duration > 0,c.total_preview_time - ifnull(d.preview_talk_time,0) - c.preview_pause_duration,0)),'00:00:00') as  pre_idle_time FROM (SELECT agent_name,agent_id,campaign_id,campaign_name,sum(if((agent_state='BREAK_N_BACK' or agent_state='BREAK') and break_type not in('JoinedConference'),if(call_end_date_time='0000-00-00 00:00:00',unix_timestamp(),if(unix_timestamp(call_end_date_time)>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(call_end_date_time)))-if(unix_timestamp(call_start_date_time)<'$reg_min_timestamp','$reg_min_timestamp',unix_timestamp(call_start_date_time)),0)) as pauseDuration,sum(if((agent_state='LOGIN_N_LOGOUT' or agent_state='LOGIN'),if(call_end_date_time='0000-00-00 00:00:00',unix_timestamp(),if(unix_timestamp(call_end_date_time)>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(call_end_date_time)))-if(unix_timestamp(call_start_date_time)<'$reg_min_timestamp','$reg_min_timestamp',unix_timestamp(call_start_date_time)),0)) as total_login_time,sum(if(agent_state='LOGIN_N_LOGOUT',if(unix_timestamp(call_start_date_time)+ready_time>'$reg_max_timestamp','$reg_max_timestamp',if(unix_timestamp(call_start_date_time)+ready_time<$reg_min_timestamp,$reg_min_timestamp,unix_timestamp(call_start_date_time)+ready_time))-if(unix_timestamp(call_start_date_time)<'$reg_min_timestamp','$reg_min_timestamp',unix_timestamp(call_start_date_time)),0)) as ready_time,sum(if((agent_state='BREAK_N_BACK' or agent_state='BREAK') and break_type ='JoinedConference',if(call_end_date_time='0000-00-00 00:00:00',unix_timestamp(),if(unix_timestamp(call_end_date_time)>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(call_end_date_time)))-if(unix_timestamp(call_start_date_time)<'$reg_min_timestamp','$reg_min_timestamp',unix_timestamp(call_start_date_time)),0)) as conf_duration,sum(if((agent_state='PREVIEW' or agent_state='PREVIEW_N_PROGRESSIVE' or agent_state='PREVIEW_N_PREDICTIVE'),if(call_end_date_time='0000-00-00 00:00:00',unix_timestamp(),if(unix_timestamp(call_end_date_time)>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(call_end_date_time)))-if(unix_timestamp(call_start_date_time)<'$reg_min_timestamp','$reg_min_timestamp',unix_timestamp(call_start_date_time)),0)) as total_preview_time,sum(if((agent_state='BREAK_N_BACK' or agent_state='BREAK') and dailer_mode='PREVIEW' and break_type not in('JoinedConference'),if(call_end_date_time='0000-00-00 00:00:00',unix_timestamp(),if(unix_timestamp(call_end_date_time)>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(call_end_date_time)))-if(unix_timestamp(call_start_date_time)<'$reg_min_timestamp','$reg_min_timestamp',unix_timestamp(call_start_date_time)),0)) as preview_pause_duration FROM  agent_state_analysis_$this->table_name as a where $this->agentBetween agent_state IN ('LOGIN_N_LOGOUT','BREAK_N_BACK','LOGIN','BREAK','PREVIEW','PREVIEW_N_PROGRESSIVE','PREVIEW_N_PREDICTIVE') $this->agent_id $this->campaign_id GROUP BY agent_id,campaign_id ORDER BY agent_id) as c left join (SELECT campaign_id,agent_id,count(*) as total_call,sum(if((call_status_disposition='answered' or call_status_disposition='transfer') and (dialer_type='PREVIEW'),1,0)) as answd_prev,sum(if((call_status_disposition='answered' or call_status_disposition='transfer') and (dialer_type='PROGRESSIVE'),1,0)) as answd_pro,sum(if((call_status_disposition='answered' or call_status_disposition='transfer') and (dialer_type='PREDICTIVE'),1,0)) as answd_pred,sum(if(cust_disposition<>'' and cust_disposition<>'None',1,0)) as disp_set,sum(if(cust_disposition='' or cust_disposition='None',1,0)) as disp_not_set,sum(if(wrapup_time!='',1,0)) as wrap_count,sum(if(unix_timestamp(call_end_date_time)!=0,if(unix_timestamp(call_end_date_time)>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(call_end_date_time))-if(unix_timestamp(call_start_date_time)<'$reg_min_timestamp',if(unix_timestamp(call_end_date_time) < '$reg_min_timestamp',unix_timestamp(call_end_date_time),'$reg_min_timestamp'),unix_timestamp(call_start_date_time)),0)) as Acdtime,sum(if(unix_timestamp(call_start_date_time)+call_duration <'$reg_max_timestamp',(if(unix_timestamp(call_start_date_time)+wrapup_time+call_duration>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(call_start_date_time)+wrapup_time+call_duration)-if(unix_timestamp(call_start_date_time)+call_duration<'$reg_min_timestamp','$reg_min_timestamp',unix_timestamp(call_start_date_time)+call_duration)),0)) as Acwtime,sum(if(unix_timestamp(call_start_date_time)+wrapup_time+call_duration>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(call_start_date_time)+call_duration+wrapup_time)-if(unix_timestamp(call_start_date_time)<'$reg_min_timestamp','$reg_min_timestamp',unix_timestamp(call_start_date_time))) as total_call_duration,sum(if(dialer_type='PREVIEW',if(ringend_time!='0000-00-00 00:00:00',if(if(unix_timestamp(ringend_time)>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(ringend_time))-if(unix_timestamp(ringstart_time)<'$reg_min_timestamp',if(unix_timestamp(ringend_time) < '$reg_min_timestamp',unix_timestamp(ringend_time),'$reg_min_timestamp'),unix_timestamp(ringstart_time)) > 0 ,if(unix_timestamp(ringend_time)>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(ringend_time))-if(unix_timestamp(ringstart_time)<'$reg_min_timestamp',if(unix_timestamp(ringend_time) < '$reg_min_timestamp',unix_timestamp(ringend_time),'$reg_min_timestamp'),unix_timestamp(ringstart_time)),'0'),'0'),0)) as ring_time,sum(if(hold_time!=0,if(unix_timestamp(call_start_date_time)+hold_time>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(call_start_date_time)+hold_time)-if(unix_timestamp(call_start_date_time)<'$reg_min_timestamp',(if(unix_timestamp(call_end_date_time)<'$reg_min_timestamp',(unix_timestamp(call_start_date_time)+hold_time),(unix_timestamp(call_start_date_time) + hold_time))),unix_timestamp(call_start_date_time)),0)) as hold_time,sum(if(dialer_type='PREVIEW' and ((unix_timestamp(call_start_date_time)+call_duration) >=$reg_min_timestamp and (unix_timestamp(call_start_date_time)+call_duration)<$reg_max_timestamp),hold_time,0)) as preview_hold_time,sum(if(dialer_type='PREVIEW' and unix_timestamp(call_end_date_time)!=0,if(unix_timestamp(call_end_date_time)>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(call_end_date_time))-if(unix_timestamp(call_start_date_time)<'$reg_min_timestamp','$reg_min_timestamp',unix_timestamp(call_start_date_time)),0)) as preview_talk_time,sum(if(dialer_type='PREVIEW',if(unix_timestamp(call_start_date_time)+wrapup_time+call_duration>'$reg_max_timestamp','$reg_max_timestamp',unix_timestamp(call_start_date_time)+wrapup_time+call_duration)-if(unix_timestamp(call_start_date_time)+call_duration<'$reg_min_timestamp','$reg_min_timestamp',unix_timestamp(call_start_date_time)+call_duration),0)) as preview_wrap_time,sum(if(hold_time,1,0)) as calls_held,sum(if(dialer_type='PREVIEW',if((unix_timestamp(ringend_time) - unix_timestamp(ringstart_time))>0,unix_timestamp(ringend_time) - unix_timestamp(ringstart_time),0),0)) as ring_duration FROM $this->table_name as a where agent_id!='' and ((call_status_disposition!='supervised_transfer' and  call_status_disposition!='noans') or isnull(call_status_disposition)) $this->campaignBetween $this->agent_id $this->campaign_id GROUP BY agent_id,campaign_id ORDER BY agent_id) as d on c.agent_id = d.agent_id and c.campaign_id=d.campaign_id";
            return $query;
        } else if ($this->report_name == "campaign_call_summary") {
            $query = $this->reportArr[$this->report_name]['query'][$this->campaign_type];
            if (substr($query, -3, 3) == "and") {
                $query = substr($query, 0, -3);
            }

            $group_by = $this->reportArr[$this->report_name]['group by'][$this->campaign_type];
            $order_by = $this->reportArr[$this->report_name]['order by'];
        } 

        $table_name = $this->reportArr[$this->report_name]['table_name'];
        eval("\$table_name = \"$table_name\";");

        if (substr($this->filterval, -3, 3) == "and") {
            $filterval = substr($this->filterval, 0, -3);
        }

        if($this->report_name === "agent_session_report" || $this->report_name === "agent_login_report"){
            $campaign_id       = filter_var($this->campaign_id, FILTER_SANITIZE_NUMBER_INT);
            if (substr($this->campaign_id, -3, 3) == "and") {
                $this->campaign_id = substr($this->campaign_id, 0, -3);
            }
            $where = $this->reportArr[$this->report_name]['where'] . " and " . $this->filterval . $this->campaign_id;
        } else {
            $where = $this->reportArr[$this->report_name]['where'] . " and " . $this->filterval;
        }
        $where = trim($where);
        eval("\$where = \"$where\";");
        $where    = rtrim($where, "and");

        $order_by = $this->reportArr[$this->report_name]['order by'];
        eval("\$order_by = \"$order_by\";");
        return $query . $table_name . " " . $where . @$group_by . $order_by;
    }

    public function fetchQueryRecords($query, $page = 1, $limit = 1000)
    {
        if ($this->report_name == "agent_break_report") {
            $headerStr = $this->agentBreakReport("header");
        } else if ($this->report_name == "campaign_call_summary") {
            $headerStr = $this->reportArr[$this->report_name]['header'][$this->campaign_type];
        } else {
            $headerStr = $this->reportArr[$this->report_name]['header'];
        }
        $header = explode(",", $headerStr);
        $query  = trim($query);
        if (substr($query, -3, 3) == "and") {
            $query = substr($query, 0, -3);
        }
        
        // Add pagination to the query
        $offset = ($page - 1) * $limit;
        $pagedQuery = $query . " LIMIT $limit OFFSET $offset";
        
        // First, get total count for pagination info
        $countQuery = "SELECT COUNT(*) as total FROM (" . $query . ") as count_table";
        $countResult = mysqli_query($this->conn, $countQuery);
        $totalRecords = 0;
        if ($countResult) {
            $countRow = mysqli_fetch_assoc($countResult);
            $totalRecords = $countRow['total'];
        }
        
        $this->reportquery = mysqli_query($this->conn, $pagedQuery);
        if ($this->reportquery->num_rows) {
            $resultArray = [];
            $processedCount = 0;
            
            while ($row = mysqli_fetch_row($this->reportquery)) {
                $arr = array_combine($header, $row);
                $resultArray[] = $arr;
                $processedCount++;
                
                // Check if we're approaching memory limits
                if ($processedCount % 500 == 0) {
                    // Force garbage collection every 500 records
                    gc_collect_cycles();
                    
                    // Check memory usage
                    $memoryUsage = memory_get_usage(true);
                    if ($memoryUsage > 256 * 1024 * 1024) { // 256MB
                        error_log("Memory usage high: " . ($memoryUsage / 1024 / 1024) . "MB");
                        break;
                    }
                }
            }
            
            $totalPages = ceil($totalRecords / $limit);
            $hasMore = $page < $totalPages;
            
            return [
                'data' => $resultArray,
                'pagination' => [
                    'current_page' => $page,
                    'total_records' => $totalRecords,
                    'total_pages' => $totalPages,
                    'has_more' => $hasMore,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ];
        } else {
            return [
                'data' => [],
                'pagination' => [
                    'current_page' => $page,
                    'total_records' => 0,
                    'total_pages' => 0,
                    'has_more' => false,
                    'limit' => $limit,
                    'offset' => $offset
                ]
            ];
        }
    }

// Report Download
    public function downloadReportIntoCSV($query)
    {

        /*$query=$this->reportArr[$this->report_name]['query'];
        eval("\$query = \"$query\";");

        $table_name=$this->reportArr[$this->report_name]['table_name'];
        eval("\$table_name = \"$table_name\";");

        if(substr($this->filterval,-3,3)=="and") $filterval=substr($this->filterval,0,-3);
        $where=$this->reportArr[$this->report_name]['where']." and".$filterval;
        eval("\$where = \"$where\";");

        $group_by=$this->reportArr[$this->report_name]['group by'];
        eval("\$group_by = \"$group_by\";");

        $order_by=$this->reportArr[$this->report_name]['order by'];
        eval("\$order_by = \"$order_by\";");*/

        if (file_exists("/var/www/html/dwld_path_define.php")) {
            include_once "/var/www/html/dwld_path_define.php";
            if (!file_exists($this->CZ_DWLD_DATA_DIR)) {
                mkdir($this->CZ_DWLD_DATA_DIR);
            }
        } else {
            $this->CZ_DWLD_DATA_DIR = "";
        }
        $download_data    = ($this->CZ_DWLD_DATA_DIR != "" ? $this->CZ_DWLD_DATA_DIR : "/tmp");
        $report_file_name = $this->report_name . date("Y_m_d_H_i_s") . ".csv";
        if ($this->report_name == "agent_break_report") {
            $head = $this->agentBreakReport("header") . "\n";
        } else if ($this->report_name == "campaign_call_summary") {
            $head = $this->reportArr[$this->report_name]['header'][$this->campaign_type] . "\n";

        } else {
            $head = $this->reportArr[$this->report_name]['header'] . "\n";
        }

        $cn  = mysqli_connect("localhost", "root", "sqladmin", "czentrix_campaign_manager");
        $sql = mysqli_query($cn, "$query INTO OUTFILE '$download_data/$report_file_name' FIELDS TERMINATED BY ',' ENCLOSED BY '\"' ESCAPED BY '\"' LINES TERMINATED BY '\r\n'") or die(mysqli_error($cn));
        header("Content-type: application/csv");
        header("Content-Disposition: attachment; filename=" . $report_file_name);
        header("Expires: 0");
        header('Content-Transfer-Encoding: binary');
        header('Cache-Control: must-revalidate, post-check=0, pre-check=0');
        header('Pragma: public');
        print($head);
        system("cat $download_data/$report_file_name");
        exit;
    }

    public function agentBreakReport($filter)
    {
        $campaign_id = filter_var($this->campaign_id, FILTER_SANITIZE_NUMBER_INT);
        $query       = "select b.campaign_id,a.campaign_name,group_concat(b.reason) as reason,b.allowed_time from break_reasons as b LEFT JOIN campaign as a on a.campaign_id=b.campaign_id where (reason<>'' or reason is not null) and b.campaign_id='$campaign_id' group by b.campaign_id";
        if (substr($query, -3, 3) == "and") {
            $query = substr($query, 0, -3);
        }

        $rs = mysqli_query($this->conn, $query);
        if ($rs->num_rows) {
            $camp          = mysqli_fetch_object($rs);
            $break_reasons = array();
            $break_reasons = explode(",", $camp->reason);
            if (count($break_reasons)) {
                $break_reasons[] = "supervised_transfer";
                $break_reasons[] = "transfer";
                $field_name      = '';
                $fieldList       = '';
                $fieldHeadings   = '';
                $custVal         = '';
                $custVal         = '';
                $fieldList       = "ifnull(agent_id,'Total') as agent_id,agent_name,campaign_name,";
                sort($break_reasons);
                $fieldHeadings = implode(",", $break_reasons);
                foreach ($break_reasons as $val) {
                    $field_name = preg_replace("/\s+/", "_", $val);
                    $field_name = preg_replace("/-/", "_", $field_name);
                    $field_name = preg_replace("/\(+/", "_", $field_name);
                    $field_name = preg_replace("/\)+/", "_", $field_name);
                    //$fieldList = $fieldList."count(break_type='".$val."' OR NULL) as ".$field_name.",";
                    $fieldList = $fieldList . "HOUR_MINUTES(sum(if(break_type='" . mysqli_real_escape_string($this->conn, $val) . "',unix_timestamp(call_end_date_time) - unix_timestamp(call_start_date_time),0))) as " . $field_name . ",";
                    $custVal   = $custVal . "'" . mysqli_real_escape_string($this->conn, $val) . "',";
                }
                $custVal   = preg_replace("/,$/", "", $custVal);
                //$cond      = (preg_match("/current_report/",$this->agent_table_name))?$this->filerObj->getEntryTime():$this->filerObj->getFilterCondition();
                                $cond=$this->filerObj->getEntryTime();
                                if(substr($cond, -4, 3) == "and"){
                                        $cond = substr($cond, 0, -4);
                                }
                $fieldList = $fieldList . "HOUR_MINUTES(sum(unix_timestamp(call_end_date_time) - unix_timestamp(call_start_date_time))) as Total";
                $start     = "select $fieldList from $this->agent_table_name  as a where a.campaign_id='" . $campaign_id . "' and a.agent_id<>'0' and agent_state='BREAK_N_BACK' and (break_type!='' and break_type is not null and break_type in ($custVal)) and $cond ";
                $end       = " group by a.agent_id WITH ROLLUP ";
                if (trim(substr($start, -4, 4)) == "and") {
                    $start = substr($start, 0, -4);
                }
                $query         = $start . $end;
                $fieldHeadings = "Agent ID, Agent Name,Campaign Name," . $fieldHeadings . ",Total";
                return ($filter == "query") ? $query : $fieldHeadings;
            }
        } else {
            return "There are no break reasons available for this campaign !!";
        }
    }

}