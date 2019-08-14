<?php
class Payair_Gate_Model_Display	{

  public function toOptionArray() 
  {
    
	return array(     
		array('value' => '0', 'label' => '-- Select Display Method --'),
		array('value' => 'banner', 'label' => 'Banner'),
        array('value' => 'button', 'label' => 'Button'),
    );
  }
}