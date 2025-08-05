<?php
//include_once "reportarr.php";
class ACD_customer{
private $reportheader;
private $query;
private $where;
private $tblname;
private $orderby;
private $current_month;
private $camp_name;
private $camp_id;
private $start_date;
private $end_date;
private $start_time;
private $end_time;
private $start_duration;
private $end_duration;
private $cust_phone;
private $groupby;
private $dwnl_reports;

public function __construct()
{

}

public function setDownloadReport($dwnl_reports)
{
    $this->dwnl_reports = $dwnl_reports;
}
public function setCurrentMonth($current_month)
{
  $this->current_month=$current_month;
}
public function setCampName($camp_name){
  $this->camp_name=$camp_name;
}
public function setHeader($reportheader){
  $this->reportheader=$reportheader;
}
public function setQuery($query){
  $this->query=$query;
}
public function setWhere($where){
  $this->where=$where;
}
public function setTableName($tblname,$current_month){
	if($current_month){
       $this->tblname=($tblname==" from current_report")?" from current_report":" from agent_state_analysis_current_report";	   
	}
	else{
	     $this->tblname=($tblname==" from current_report")?" From ".date("Y_m"):" From agent_state_analysis_".date("Y_m");
	}
}

public function setOrderBy($orderby){
  $this->orderby=$orderby;
}
public function setGroupBy($groupby){
  $this->groupby=$groupby;
}
public function setStartDate($start_date){
  $this->start_date=$start_date;
}
public function setEndDate($end_date){
  $this->end_date=$end_date;
}
public function setStartTime($start_time){
  $this->start_time=$start_time;
}
public function setEndTime($end_time){
  $this->end_time=$end_time;
}
public function setStartDuration($start_duration){
  $this->start_duration=$start_duration;
}
public function setEndDuration($end_duration){
  $this->end_duration=$end_duration;
}
public function setCustomerPhone($cust_phone){
	$this->cust_phone=$cust_phone;
}
public function setUniqueCall($unique_call){
	$this->unique_call=$unique_call;
}

public function getHeader()
{
 return $this->reportheader;
}
public function getDownloadReport()
{
    return $this->dwnl_reports;
}
public function getQuery(){
 return $this->query;
}
public function getWhere(){
 return $this->where;
}
public function getTableName(){
 return $this->tblname;
}
public function getOrderBy(){
 return $this->orderby;
}
public function getGroupBy(){
 return $this->groupby;
}
public function getCurrentMonth(){
  return $this->current_month;
}
public function getCampNameWiseId(){
  
  return $this->camp_name; 
}
public function getStartDate(){
  return $this->start_date;
}
public function getEndDate(){
  return $this->end_date;
}
public function getStartTime(){
  return $this->start_time;
}
public function getEndTime(){
  return $this->end_time;
}
public function getStartDuration(){
  return $this->start_duration;
}
public function getEndDuration(){
  return $this->end_duration;
}
public function getCustomerPhone(){
  return $this->cust_phone;
}
public function getUniqueCall(){
 return $this->unique_call;
}

public function setReportDateTime($start_date,$end_date,$start_time,$end_time,$start_duration,$end_duration)
{

}

}
?>