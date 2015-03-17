<?php

namespace Web\Widget;

abstract class BarWidget extends AbstractWidget {

    abstract function getTitle();

    /**
     * Should be overridden by child classes to output HTML
     *
     * @return string The generated HTML
     */
    protected function _render() {
        $id = uniqid();

        $data = '[';
        $labels = '[';
        $pairsData = array();
        $pairsLabels = array();
        foreach($this->getData() as $k=>$v){
            $pairsData[] = '' . $v.'';
            $pairsLabels[] = '\'' . $k.'\'';
        }
        $data .= implode(', ', $pairsData);
        $labels .= implode(', ', $pairsLabels);
        $data .= ']';
        $labels .= ']';

        $title = $this->getTitle();

        return <<<EOF
<div id="bar-{$id}" style="height: 400px; width: 100%;"></div>
<script language="javascript">
$(document).ready(function(){
  $.jqplot ('bar-{$id}', [{$data}],
    {
        title: '{$title}',
        animate: false,
        seriesDefaults:{
            renderer:$.jqplot.BarRenderer,
            pointLabels: { show: true },
        },
        axes: {
            xaxis: {
                renderer: $.jqplot.CategoryAxisRenderer,
                ticks: {$labels}
            }
        },
        highlighter: { show: false }
    }
  );
});
</script>
EOF;
    }
}