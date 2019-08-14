<?php
class Payair_Gate_Model_Environment	{

  public function toOptionArray() 
  {
    
	return array(     
		array('value' => '0', 'label' => '-- Select Enviornment --'),
		array('value' => 'development', 'label' => 'Development'),
        array('value' => 'production', 'label' => 'Production'),
		array('value' => 'qa', 'label' => 'QA'),
    );
  }
}