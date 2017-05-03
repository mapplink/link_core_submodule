<?php
/**
 * @package Web\Widget
 * @author Matt Johnston
 * @copyright Copyright (c) 2014 LERO9 Ltd.
 * @license http://opensource.org/licenses/BSD-3-Clause BSD-3-Clause - Please view LICENSE.md for more information
 */

namespace Web\Widget;


abstract class PieWidget extends AbstractWidget
{

    abstract function getTitle();

    /**
     * Should be overridden by child classes to output HTML
     *
     * @return string The generated HTML
     */
    protected function _render() {
        $id = uniqid();

        $data = '[';
        $pairs = array();
        foreach($this->getData() as $k=>$v){
            $pairs[] = '[\''.$k.'\', ' . $v.']';
        }
        $data .= implode(',', $pairs);
        $data .= ']';

        $title = $this->getTitle();

        return <<<EOF
<div id="pie-{$id}" style="height: 400px; width: 100%;"></div>
<script language="javascript">
$(document).ready(function(){
  jQuery.jqplot ('pie-{$id}', [{$data}],
    {
      seriesDefaults: {
        // Make this a pie chart.
        renderer: jQuery.jqplot.PieRenderer,
        rendererOptions: {
          showDataLabels: true,
          sliceMargin: 1,
          fill: false,
          lineWidth: 5,
          dataLabels: 'value'
        }
      },
      title: '{$title}',
      legend: { show:true, location: 'e' }
    }
  );
});
</script>
EOF;
    }

}