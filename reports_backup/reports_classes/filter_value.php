<?php
class FilterClass{
private $table_name;
private $startdate;
private $enddate;
private $starttime;
private $endtime;
private $startduration;
private $endduration;
private $between_duration;
private $disposition;
private $list_id;
private $cust_disposition;
private $dialer_type;
private $unique_calls;
private $campaign_id;
private $agent_id;
private $agentBetween;
private $campaignBetween;
private $magic_call;

public function setTablesName($current_date){
	$this->table_name=($current_date)?"current_report":date("Y_m");
}

public function getTablesName(){
	return $this->table_name;
}

public function setStartDate($startdate){
   $this->startdate=(empty($startdate))?date('Y-m-d', strtotime(date("Y-m-d"))):$startdate;

}

public function getStartDate(){
  return $this->startdate;
}

public function setEndDate($enddate){
   $this->enddate=empty($enddate)?date("Y-m-d"):$enddate;

}

public function getEndDate(){
	return $this->enddate;
} 

public function setStartTime($starttime){
	$this->starttime=($starttime=="")?"00:00:00":$starttime;
}

public function getStartTime(){
  return $this->starttime;
}

public function setEndTime($endtime){
	$this->endtime=($endtime=="")?"23:59:59":$endtime;
}

public function getEndTime(){
 return $this->endtime;
}

public function setBetweenDates(){

}

public function getBetweenDates(){
  //return " entrytime between unix_timestamp('$this->startdate $this->starttime') and unix_timestamp('$this->enddate $this->endtime') and";
  return " ((unix_timestamp('$this->startdate $this->starttime') >= entrytime and if(call_end_date_time='0000-00-00 00:00:00',unix_timestamp(),unix_timestamp(call_end_date_time)) > unix_timestamp('$this->startdate $this->starttime')) or (entrytime >= unix_timestamp('$this->startdate $this->starttime') and entrytime <= unix_timestamp('$this->enddate $this->endtime'))) and";
}

public function getEntryTime(){
	return " entrytime between unix_timestamp('$this->startdate $this->starttime') and unix_timestamp('$this->enddate $this->endtime') and";
}
public function setBegningDuration($startduration){
	$startduration=($startduration=="")?"00:00:00":$startduration;
	$this->startduration=" time_to_sec(time(from_unixtime(entrytime)))>=time_to_sec('$startduration') and";
}

public function getBegningDuration(){
	return $this->startduration;
}

public function setEndDuration($endduration){
	$endduration=($endduration=="")?"23:59:59":$endduration;
	$this->endduration=" time_to_sec(time(from_unixtime(entrytime)))<=time_to_sec('$endduration') and";
}

public function getEndDuration(){
	return $this->endduration;
}

public function setBetweenDuration($startDuration,$endDuration){
	$startDuration=($startDuration=="")?"00:00:00":$startDuration;
	$endDuration=($endDuration=="")?"23:59:59":$endDuration;
	$this->between_duration=" time_to_sec(time(from_unixtime(entrytime))) between time_to_sec('$startDuration') and time_to_sec('$endDuration') and";
}

public function getBetweenDuration(){
	return $this->between_duration;
}


 public function setCampaignId($campaign_id, $magic_call) {
        if ($magic_call == 1 && $campaign_id != "") {
            $this->campaign_id = " a." . ($magic_call == 1 ? "department_id" : "campaign_id") . "='$campaign_id' and";
        } else {
            $this->campaign_id = "";
        }
 }

public function getCampaignId(){
	return $this->campaign_id;
}

public function setAgentId($agent_id){
	$this->agent_id=($agent_id!="")?" a.agent_id='$agent_id' and":"";
}

public function getAgentId(){
	return $this->agent_id;
}

public function setListId($list_id){
	$this->list_id=($list_id!="")?" a.list_id='$list_id' and":"";
}

public function getListId(){
	return $this->list_id;
}

// both disposition and custdisposition are same
public function setDisposition($disposition){
	if($disposition == "unknown")
		$this->disposition=" isnull(a.call_status_disposition) and";
	else if($disposition=="")
		$this->disposition="";
	else
		$this->disposition=" a.call_status_disposition='$disposition' and";
}

public function getDisposition(){
	return $this->disposition;
}


public function setCampaignType($campaign_type){
     $this->campaign_type=($campaign_type!="")?" a.campaign_type='$campaign_type' and  ":"";
}

public function getCampaignType(){
	return $this->campaign_type;
}

public function setDialerType($dialer_type){
	$this->dialer_type=($dialer_type!="")?" a.dialer_type='$dialer_type' and":"";
}

public function getDialerType(){
	return $this->dialer_type;
}

public function setCustDisposition($cust_disposition){
	if($cust_disposition == "unknown")
		$this->cust_disposition=" isnull(a.call_status_disposition) and";
	else if($cust_disposition=="")
		$this->cust_disposition="";
	else
		$this->cust_disposition=" a.call_status_disposition='$cust_disposition' and";
}

public function getCustDisposition(){
	return $this->cust_disposition;

}

public function setCustomerPh($customer_ph){
	if($customer_ph!=""){
		$customer_ph = str_replace( "*", "%", $customer_ph );
		$this->customer_ph=" a.cust_ph_no like '$customer_ph' and";
	}
	else 
		$this->customer_ph=$customer_ph;

}

public function getCustomerPh(){
   return $this->customer_ph;

}

public function setAgentBetween(){
	 $this->agentBetween=" ((unix_timestamp('$this->startdate $this->starttime') >= entrytime and if(call_end_date_time='0000-00-00 00:00:00',unix_timestamp(),unix_timestamp(call_end_date_time)) > unix_timestamp('$this->startdate $this->starttime')) or (entrytime >= unix_timestamp('$this->startdate $this->starttime') and entrytime <= unix_timestamp('$this->enddate $this->endtime'))) and";
}

public function getAgentBetween(){
	 return $this->agentBetween;

}

public function setCampaignBetween(){
	$this->campaignBetween=" and ((unix_timestamp('$this->startdate $this->starttime') >= unix_timestamp(call_start_date_time) and (unix_timestamp(call_start_date_time) + call_duration + wrapup_time) > unix_timestamp('$this->startdate $this->starttime')) or (unix_timestamp(call_start_date_time) >= unix_timestamp('$this->startdate $this->starttime') and unix_timestamp(call_start_date_time) <=unix_timestamp('$this->enddate $this->endtime'))) ";
}

public function getCampaignBetween(){
  return $this->campaignBetween;
}


public function setFilterCondition($current_date,$startdate,$enddate,$starttime,$endtime,$startduration,$endduration,$campaign_id,$agent_id,$list_id,$disposition,$campaign_type,$dialer_type,$cust_disposition,$customer_ph,$unique_calls,$magic_call){
	$this->startdate=$startdate;
	$this->enddate=$enddate;
	$this->starttime=$starttime;
	$this->endtime=$endtime;
	$this->setTablesName($current_date);
	$this->setStartDate($startdate);
	$this->setEndDate($enddate);
	$this->setStartTime($starttime);
	$this->setEndTime($endtime);
	$this->setBegningDuration($startduration);
	$this->setEndDuration($endduration);
	$this->setBetweenDuration($startduration,$endduration);
	$this->setCampaignId($campaign_id,$magic_call);
	$this->setAgentId($agent_id);
	$this->setListId($list_id);
	$this->setDisposition($disposition);
	$this->setCampaignType($campaign_type);
	$this->setDialerType($dialer_type);
	$this->setCustDisposition($cust_disposition);
	$this->setCustomerPh($customer_ph);
	$this->setUniqueCalls($unique_calls);
	$this->setAgentBetween();
	$this->setCampaignBetween();
}

public function getFilterCondition(){
	$camp_type=trim($this->getCampaignType());
	$camp_type=($camp_type!="a.campaign_type='ALL' and")?" ".$camp_type:'';
	//print $camp_type."=======================================+++++++++++++++++==================";
	return $this->getEntryTime().$this->getBegningDuration().$this->getEndDuration().$this->getBetweenDuration().$this->getCampaignId().$this->getAgentId().$this->getDisposition().$this->getListId().$camp_type.$this->getDialerType().$this->getCustDisposition().$this->getCustomerPh().$this->getUniqueCalls();
}

public function setUniqueCalls($unique_calls){
	$this->unique_calls=($unique_calls!="")?" (a.transfer_from is null or a.transfer_from ='') and":"";
}

public function getUniqueCalls(){
	return $this->unique_calls;
}

}

?>