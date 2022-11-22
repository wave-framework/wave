<?php

namespace Wave;

class Debug
{

    private $config;

    private $queries = array();
    private $query_count = 0;
    private $used_files = array();
    private $execution_start;
    private $checkpoints = array();

    private static $instance = null;

    /**
     * @return \Wave\Debug
     */
    public static function getInstance()
    {
        if (self::$instance === null)
            self::init();

        return self::$instance;
    }

    public function __construct(array $config)
    {
        $this->config = $config;
        $this->resetExecutionTime($config['start_time']);
    }

    public static function init(array $config = array())
    {
        $defaults = array(
            'start_time' => microtime(true),
            'log_queries' => Core::$_MODE !== Core::MODE_PRODUCTION,
            'log_checkpoints' => Core::$_MODE !== Core::MODE_PRODUCTION
        );

        self::$instance = new self(array_merge($defaults, $config));
    }

    public function getMemoryUsage()
    {
        return round(memory_get_peak_usage() / pow(1024, 2), 2);
    }

    public function getCurrentMemoryUsage()
    {
        return round(memory_get_usage() / pow(1024, 2), 2);
    }

    /**
     * Returns the time in miliseconds since the initation of the object. Used to track program execution time.
     * @return int
     */
    public function getExecutionTime()
    {
        return round((microtime(true) - $this->execution_start) * 1000, 0);
    }

    public function resetExecutionTime($reset_time = null)
    {
        if ($reset_time === null)
            $reset_time = microtime(true);

        $this->execution_start = $reset_time;
    }

    public function getCheckpoints()
    {
        return $this->checkpoints;
    }

    public function addCheckpoint($name)
    {
        if ($this->config['log_checkpoints']) {
            $this->checkpoints[] = array(
                'name' => $name,
                'time' => $this->getExecutionTime(),
                'memory' => $this->getCurrentMemoryUsage()
            );

        }
    }

    /**
     * Adds the details of a used file in to an array
     * @param object $filename
     * @param object $caller [optional]
     * @return
     */
    public function addUsedFile($filename, $caller = null)
    {
        $this->used_files[] = array('filename' => $filename, 'caller' => $caller);
    }

    /**
     * Returns all files used in the process
     * @return
     */
    public function getUsedFiles()
    {
        $out = array();
        foreach (get_included_files() as $i => $file) {
            $out[] = array('number' => $i + 1, 'filename' => str_replace(SYS_ROOT, '', $file));
        }
        return $out;
    }

    public function addQuery($time, $statement)
    {

        $this->query_count++;

        if ($this->config['log_queries']) {
            $sql = $statement->queryString;
            $rows = $statement->rowCount();
            $success = $statement->errorCode() == \PDO::ERR_NONE ? true : false;
            $time = round($time * 1000, true);

            $sql = str_replace(chr(0x0A), ' ', $sql);
            $sql = str_replace('  ', ' ', $sql);

            $this->query_count = array_push(
                $this->queries, array(
                    'success' => $success,
                    'time' => $time,
                    'sql' => $sql,
                    'rows' => $rows
                )
            );
        }

        $this->addCheckpoint('query.' . $this->query_count);
    }


    public function getNumberOfFiles()
    {
        return count(get_included_files());
    }


    public function getNumberOfQueries()
    {
        return $this->query_count;
    }

    /**
     * Returns the queris involved in the render, sets a colour for bad ones
     */
    public function getQueries()
    {

        $out = array();
        for ($i = 0; $i < count($this->queries); $i++) {

            $colour = $this->queries[$i]['success'] ? "green" : "red";
            $sql = $this->queries[$i]['sql'];
            $rows = $this->queries[$i]['rows'] . ' row' . ($this->queries[$i]['rows'] == 1 ? '' : 's');
            $time = $this->queries[$i]['time'] . ' ms';

            $out[] = array(
                'colour' => $colour,
                'number' => $i + 1,
                'sql' => addslashes($sql),
                'time' => $time,
                'rows' => $rows
            );
        }

        return $out;

    }

    public function render()
    {
        Hook::triggerAction('debugger.render', array(&$this));
        ?>
        <!--DEBUG PANEL-->
        <style type="text/css"><?php echo self::getCSS(); ?></style>
        <div id="_wave_debugpanel">
            <div id="_wave_debugclosetrigger" class="item" style="margin-top:-1px;border-right:none;">
                <div id="_wave_debugclose"> x</div>
            </div>
            <div class="item">
                <div class="_wave_debugicon" id="_wave_debugclock"></div>
                <div class="itemlabel"><?php echo $this->getExecutionTime(); ?>ms</div>
            </div>
            <div class="item">
                <div class="_wave_debugicon" id="_wave_debugmemory"></div>
                <div class="itemlabel"><?php echo $this->getMemoryUsage(); ?>mb</div>
            </div>
            <div class="item">
                <div class="_wave_debugicon" id="_wave_debugdb"></div>
                <div class="itemlabel"><?php echo $this->getNumberOfQueries(); ?></div>
            </div>
            <div class="item">
                <div class="_wave_debugicon" id="_wave_debugfiles"></div>
                <div class="itemlabel"><?php echo $this->getNumberOfFiles(); ?></div>
            </div>
            <div style="margin-bottom:-8px; visibility:hidden;" id="_wave_debugitemdetails"></div>
        </div>

        <script type="text/javascript">
            //      <![CDATA[
            (function () {
                var details = [];
                var oldrow;
                var contents;

                details['_wave_debugdb'] = "<?php foreach($this->getQueries() as $query): ?><div class=\"itemrow query\" style=\"color:<?php echo $query['colour']; ?>;\">[:<?php echo $query['number']; ?>]  <?php echo $query['sql']; ?><span class=\"r\">     (<?php echo $query['time']; ?>, <?php echo $query['rows']; ?>)</span></div><?php endforeach; ?>";

                details['_wave_debugfiles'] = '<?php foreach($this->getUsedFiles() as $file): ?><div class="itemrow">[<?php echo $file['number']; ?>]  \'<?php echo $file['filename']; ?>\'</div><?php endforeach; ?>';

                details['_wave_debugcp'] = '<?php foreach($this->getCheckpoints() as $checkpoint): ?><div class="itemrow"><?php echo str_pad($checkpoint['name'], 30, ' '); ?> => <?php echo str_pad($checkpoint['memory'] . 'mb', 7, ' '); ?> | <?php echo str_pad($checkpoint['time'] . 'ms', 7, ' '); ?></div><?php endforeach; ?>';

                bind();

                function showDetails(row_id) {
                    var itemdetails = document.getElementById("_wave_debugitemdetails");
                    if (itemdetails.style.height == "auto" && oldrow == row_id) {
                        itemdetails.style.height = "0px";
                        itemdetails.style.marginBottom = "-8px";
                        itemdetails.style.visibility = "hidden";
                        itemdetails.innerHTML = "";
                    } else {
                        itemdetails.style.height = "auto";
                        itemdetails.style.marginBottom = "0px";
                        itemdetails.style.visibility = "visible";
                        itemdetails.innerHTML = details[row_id];
                        oldrow = row_id
                    }
                }

                function hide() {
                    var bar = document.getElementById("_wave_debugpanel");
                    contents = bar.innerHTML;
                    bar.innerHTML = "<div id=\"_wave_debugclosetrigger\" class=\"item\" style=\"margin-top:-1px;border-right:none;\"><div id=\"_wave_debugclose\"> + </div></div>";
                    document.getElementById('_wave_debugclosetrigger').onclick = show;
                }

                function show() {
                    var bar = document.getElementById("_wave_debugpanel");
                    bar.innerHTML = contents;
                    bind();
                }

                function bind() {
                    var e_db = document.getElementById('_wave_debugdb'),
                        e_fi = document.getElementById('_wave_debugfiles'),
                        e_ti = document.getElementById('_wave_debugclock'),
                        e_me = document.getElementById('_wave_debugmemory');
                    e_db.onclick = function () {
                        showDetails(e_db.id);
                    };
                    e_fi.onclick = function () {
                        showDetails(e_fi.id);
                    };
                    e_ti.onclick = function () {
                        showDetails('_wave_debugcp');
                    };
                    e_me.onclick = function () {
                        showDetails('_wave_debugcp');
                    };

                    document.getElementById('_wave_debugclosetrigger').onclick = hide;
                }

            })();
            //      ]]>
        </script>
        <!--END DEBUG PANEL-->
        <?php
    }

    public static function getCSS()
    {

        return <<<CSS
#_wave_debugmemory{background-position: 0px -48px;}
#_wave_debugfiles{background-position: 0px -32px;}
#_wave_debugclock{background-position: 0px -16px;}
#_wave_debugdb{background-position: 0px 0px;}
div._wave_debugicon{background-image: url(data:image/gif;base64,R0lGODlhEABgAOZ/AJm2xYeHh7KbWdjImbCwsNarEsy5luLi4v3+/cHBweXn6MrU4yJQdYSitbm4yJubm9jj7OPq9cXV2vP2+pOTk6qrq2xtgXh5lNTc69KxR4eIo87Oz3l4eHaWqtzc3LKUNmWCm+rx86ett7eRDpaaqGyUrFJTdOLYx6qURDdhhIl2UtnZ2aOks9nUya3F0rOts0ZyjmRngNbW1snJyaOTl4aKmZeasKmquaSnt5SQnXN1kOrt8tHR0d3k8by7usTFyO3y++nu+KKjpfn5+kt4l+Hm8VmIoPn9/mOKo+vr7JSrvPj7//X5/ufz9K+70dHc47XK073J3L/ExrjO1sbKzmxsbFZ8mbjD1nugtP38+/Pz887a3FWBmkNpikdtkp/EzqvAy7/I152gqb2mUc/O3bmseJCMnMPP4bmwmdXU2GRcg/n++oB+k/77+t7e3ra2u5CRpo2euWVpkG5wkHZrhu339xU/bF9efomvvr7S3tPT18rNzczMzKCOU////97e3iH5BAEAAH8ALAAAAAAQAGAAAAf/gH8bMjwzPz4+bz4/Gzw8G39/PCszMgdJOztJBzJ8PB6RMxsHBx4epKQKB4ORCQEVK6WEGzM+BA8+rRwBPH5+WnwPHLsUBK0BFDK+Q3wUVRwUDwmhBBRCP45vFAHIFQetFBVvFQ/lD0IVt9N/CbsyCFlaPBUB0MXHycszztC4kRsViq1QoOCHMGQEZgja8KACOXPl0AlROMjdkAkK+NBDFuCHID67evkZsu8ZhXs8DgT0oSBJkh8UiD3gEenUigQE0lUQIuTNqG+R/kgBAwBGihRWXDyhEjSSlA4wiBjpUIJICiQAmDoFkaJEFAhfvjxpcBQApA0dGCgJ4ecIHgBt/wEwgLGFx5YuXJr4aoIFri8lDDoUAeAFjB8EfprgcRECypMQVl0QIQLBTxY/dbCAKIHkDAKyJVIYqZwFwRElWKEMWaNEdAoulX0hgAChtJ8JoEt0mXIYAeLDbfxEsAogjOg6h2UjRiAXiQS0DBow8UUdgYujU3jwkWDFTocnEUI0gSCXAQAFZ6FYYSCaKgz2eBTQjLRnC+GjR5FAkd9UUIg8eGDRgAtTbABJUyEUoWAPGCxwBnr9/bHDBL4sAQQEUTx4YFATyjZBBAuAmMSGElLoCxNBLIBBFE4o0FSHFQLRwxlOxKFVJDC2JeMZUQAgxYsm+sFEBA0u4IRHHJo4JP8EGDR5JJB+LBFEBEUwCcEVSOJI4QRTRtADBD1gCeQRQAAxZRE99BBGliVaaCaVaa754hAITDDBm0VEEAWbmXSJZpMYiMmhDyIUamihQpBoigwrNOroCh64GOGklFZKqRt6zOAGpR7okcAbNzjgAAtpRLiCBTY4QIYDb4gKRwKbBnXAHWrYMA4LOODAAhwkrBDrAWOoEMMcJJCggQZwwKEBGweeUAAKHNAxRw0XXKCDDtbewYIbA4zQRwYfsDHHsHeUewcbbPDhRgIGFPABDTnIYYIJd+hgAxUv5HJABiOgIEAGNAyrgQ03kPFCH5G4IYABGRRQBrZwEIyDGQbIOkBAASOgoYEOZtSQww9uxBpJCyN88MK4Ymwgcn8ttPAHpJbGLPPMNNds880456zzzjz37PPPQAct9NBEF2300TgHAgA7); width: 16px; height: 16px; float: left; cursor: pointer;}

div#_wave_debugpanel{font-family: "Lucida Sans" Arial; font-size: 12px; opacity:.5; alpha:50; position:absolute; z-index:99999; right:1px; top:1px; background-color:#DDDDDD; padding:3px; border:1px solid #888888; -moz-border-radius: 5px; -webkit-border-radius: 5px;line-height: normal; text-align:left;}
div#_wave_debugpanel:hover{opacity:.9; alpha:90;}

div#_wave_debugpanel .item{float:right; padding:0 5px 0 5px; border-right: solid #888888 1px;}
div#_wave_debugpanel .item .itemlabel{margin:1px 0 0 3px; float:left;}

div#_wave_debugitemdetails{float:left; clear:both; margin-top: 5px; font-size:11px; width:100%; overflow: hidden; background-color: #EEEEEE; border-top:#888888 solid 1px; padding-bottom:3px;}
div#_wave_debugitemdetails .itemrow{font-family:monospace; float:left; clear:both; padding:3px 5px 0 5px;white-space: pre-wrap;}
div#_wave_debugitemdetails .r { display:block; }

div#_wave_debugclose{background-color:#DDDDDD; padding:2px;}
div#_wave_debugclose:hover{cursor: pointer; background-color: #CCCCCC;}

CSS;

    }

}