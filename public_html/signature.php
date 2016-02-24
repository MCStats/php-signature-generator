<?php
define('ROOT', './');

require('../app/mcstats.php');

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('HOURS', 168);
define('GRAPH_BORDERS_FULL', 0);
define('GRAPH_BORDERS_NONE', 1);

putenv('GDFONTPATH=' . realpath('../fonts/'));

define('IMAGE_HEIGHT', 150); // 124
define('IMAGE_WIDTH', 625); // 478

header('Access-Control-Allow-Origin: *');
header('Content-type: image/png');

// TODO testing vars
$_GET['plugin'] = 'LWC';
$_GET['graph'] = 'Global Statistics';

$graphBorders = GRAPH_BORDERS_FULL;

if (isset($_GET['borders'])) {
    $borders = $_GET['borders'];

    if ($borders == 'full') {
        $graphBorders = GRAPH_BORDERS_FULL;
    } elseif ($borders == 'none') {
        $graphBorders = GRAPH_BORDERS_NONE;
    }
}

$scale = isset($_GET['scale']) ? $_GET['scale'] : 1;

if ($scale > 10 || $scale <= 0) {
    define('REAL_IMAGE_HEIGHT', IMAGE_HEIGHT);
    define('REAL_IMAGE_WIDTH', IMAGE_WIDTH);
    error_image('Invalid scale');
} else {
    define('REAL_IMAGE_HEIGHT', IMAGE_HEIGHT * $scale);
    define('REAL_IMAGE_WIDTH', IMAGE_WIDTH * $scale);
}

if (!isset($_GET['plugin'])) {
    error_image('Error: No plugin provided');
}

$pluginName = urldecode($_GET['plugin']);
$plugin = loadPluginJson($pluginName);

if ($plugin == null) {
    error_image('Invalid plugin');
}

$pluginName = $plugin->name;

require ROOT . '../app/pChart/pData.class.php';
require ROOT . '../app/pChart/pDraw.class.php';
require ROOT . '../app/pChart/pImage.class.php';

// Create a new data set
$dataSet = new pData();

$graphName = isset($_GET['graph']) ? urldecode($_GET['graph']) : 'Global Statistics';
$graph = loadPluginGraphJson($plugin->id, $graphName);

if ($graph == null) {
    error_image('Invalid graph');
}

if ($graph->type == GraphType::Percentage_Area) {
    $graph->type = GraphType::Pie;
}

$legendLength = 0;

// TODO HOURS setting
$graphData = loadPluginGraphDataJson($plugin->id, $graph->id, HOURS);

if (count($graphData) > 0) {
    if ($graph->type == GraphType::Pie) {
        $values = array();
        $labels = array();

        $graphData = $graphData[0]->data;
        $totalSum = 0;

        foreach ($graphData as $data) {
            $totalSum += $data->sum;
        }

        foreach ($graphData as $data) {
            $columnName = $data->name;
            $value = $data->sum;
            $percent = round($value / $totalSum, 4) * 100;

            $labels[] = $columnName . ': ' . $percent . '%';
            $values[] = $percent;
        }

        $dataSet->addPoints($values, 'Values');
        $dataSet->addPoints($labels, 'Labels');
        $dataSet->setAbscissa('Labels');
    } elseif ($graph->type == GraphType::Donut) {
        $values = array();
        $labels = array();

        $graphData = $graphData[0]->data;
        $totalSum = 0;
        $mergedSums = array();

        foreach ($graphData as $data) {
            $totalSum += $data->sum;
        }

        foreach ($graphData as $data) {
            $columnName = $data->name;
            $value = $data->sum;

            $split = explode($MCSTATS_DONUT_INNER_SEPARATOR, $columnName);
            $outerColumnName = $split[0];

            if (!array_key_exists($outerColumnName, $mergedSums)) {
                $mergedSums[$outerColumnName] = 0;
            }

            $mergedSums[$outerColumnName] += $value;
        }

        foreach ($mergedSums as $outerColumnName => $sum) {
            $percent = round($sum / $totalSum, 4) * 100;

            $labels[] = $outerColumnName . ': ' . $percent . '%';
            $values[] = $percent;
        }

        $dataSet->addPoints($values, 'Values');
        $dataSet->addPoints($labels, 'Labels');
        $dataSet->setAbscissa('Labels');
    } else {
        $timestamps = array();
        $columnData = array();

        foreach ($graphData as $entry) {
            $epoch = $entry->epoch;

            foreach ($entry->data as $data) {
                $columnName = $data->name;
                $sum = $data->sum;

                if (!array_key_exists($columnName, $columnData)) {
                    $columnData[$columnName] = array();
                }

                $columnData[$columnName][] = $sum;
            }

            $timestamps[] = $epoch;
        }

        foreach ($columnData as $columnName => $data) {
            $dataSet->addPoints($data, $columnName);
        }

        $dataSet->addPoints($timestamps, 'Timestamps');
        $dataSet->setAbscissa('Timestamps');
        $dataSet->setXAxisDisplay(AXIS_FORMAT_CUSTOM, 'XAxisFormat');
    }
}

$legendXOffset = 50 + intval($legendLength * 2);

$dataSet->loadPalette('../fonts/palette.txt', true);

function XAxisFormat($value) {
    return gmdate('Y-m-d', $value);
}

$graphImage = new pImage(REAL_IMAGE_WIDTH, REAL_IMAGE_HEIGHT, $dataSet);

$graphImage->setFontProperties(array('FontName' => '../fonts/pf_arma_five.ttf', 'FontSize' => 6));
$graphImage->setGraphArea(40, $graphBorders == GRAPH_BORDERS_FULL ? 20 : 0, REAL_IMAGE_WIDTH + 6, REAL_IMAGE_HEIGHT - 15);

if ($graphBorders == GRAPH_BORDERS_FULL) {
    $graphImage->drawRectangle(5, 5, REAL_IMAGE_WIDTH - 1, REAL_IMAGE_HEIGHT - 1, array('R' => 0, 'G' => 0, 'B' => 0));
}

$graphImage->Antialias = true;

if (count($graphData) == 0) {
    $graphImage->drawText(REAL_IMAGE_WIDTH / 2, REAL_IMAGE_HEIGHT / 2, 'NO DATA AVAILABLE', array(
        'FontName' => '../fonts/OpenSans-Regular.ttf',
        'FontSize' => max(20, 20 * $scale * 0.5),
        'Align' => TEXT_ALIGN_TOPMIDDLE,
        'Alpha' => 30,
        'BoxR' => 255,
        'BoxG' => 255,
        'BoxB' => 255,
        'DrawBox' => true,
        'BoxAlpha' => 100,
        'BorderOffset' => -2
    ));
} else {
    if ($graph->type == GraphType::Pie || $graph->type == GraphType::Donut) {
        require ROOT . '../app/pChart/pPie.class.php';
        $pie = new pPie($graphImage, $dataSet);
        $pie->draw2DPie(REAL_IMAGE_WIDTH / 2, REAL_IMAGE_HEIGHT / 2 + 10, array('Radius' => 60 * $scale, 'DrawLabels' => false, 'LabelStacked' => true, 'Border' => true, 'SecondPass' => true, 'LabelColor' => PIE_LABEL_COLOR_AUTO));
        $pie->drawPieLegend(8, 12, array('Style' => LEGEND_NOBORDER, 'FontName' => '../fonts/Segoe_UI.ttf', 'FontSize' => max(6, 6 * $scale * 0.5), 'BoxSize' => 5 * $scale));
    } else { // area / line
        $scaleSettings = array('RemoveXAxis' => false, 'LabelSkip' => 50, 'Mode' => SCALE_MODE_START0, 'XMargin' => 5, 'YMargin' => 5, 'Floating' => true, 'GridR' => 200, 'GridG' => 200, 'GridB' => 200, 'DrawSubTicks' => true, 'CycleBackground' => true);
        $graphImage->drawScale($scaleSettings);
        $graphImage->drawLegend($graphBorders == GRAPH_BORDERS_FULL ? 10 : 50, 10, array('FontSize' => max(6, 6 * $scale * 0.3), 'BoxWidth' => max(5, 5 * $scale * 0.3), 'BoxHeight' => max(5, 5 * $scale * 0.3), 'Style' => LEGEND_NOBORDER, 'Mode' => LEGEND_HORIZONTAL));

        if ($graph->type == GraphType::Line) {
            $graphImage->drawLineChart();
        } elseif ($graph->type == GraphType::Area) {
            $graphImage->drawAreaChart();
        } else {
            error_image('unsupported graph type');
        }
    }
}

if ($graph->type == GraphType::Pie || $graph->type == GraphType::Donut || $graphBorders == GRAPH_BORDERS_FULL) {
    $graphImage->drawText(REAL_IMAGE_WIDTH / 2, max(5, 5 * $scale), $graph->display_name, array('FontName' => '../fonts/Forgotte.ttf', 'FontSize' => max(20, 20 * $scale * 0.5), 'Align' => TEXT_ALIGN_TOPMIDDLE, 'BoxR' => 255, 'BoxG' => 255, 'BoxB' => 255, 'DrawBox' => true, 'BoxAlpha' => 100, 'BorderOffset' => -2));
}

// Authors
// TODO authors
// $authors = $plugin->authors;
$authors = '';

if (!empty($authors)) {
    $title = ' :: ' . $pluginName . ' - ' . $authors;
} else {
    $title = ' :: ' . $pluginName;
}

// Watermark
if ($graph->type == GraphType::Pie || $graph->type == GraphType::Donut) {
    $graphImage->drawText(REAL_IMAGE_WIDTH, REAL_IMAGE_HEIGHT - 2, 'mcstats.org' . $title, array('FontName' => '../fonts/pf_arma_five.ttf', 'R' => 177, 'G' => 177, 'B' => 177, 'Alpha' => 25, 'FontSize' => max(12, 12 * $scale * 0.5), 'Align' => TEXT_ALIGN_BOTTOMRIGHT));
} else {
    $graphImage->drawText(REAL_IMAGE_WIDTH, 5, 'mcstats.org' . $title, array('FontName' => '../fonts/pf_arma_five.ttf', 'R' => 177, 'G' => 177, 'B' => 177, 'Alpha' => 25, 'FontSize' => max(12, 12 * $scale * 0.5), 'Align' => TEXT_ALIGN_TOPRIGHT));
}

$tahoma = 'tahoma.ttf';
$bounding_box = imagettfbbox(11, 0, $tahoma, $title);
$center_x = ceil((REAL_IMAGE_WIDTH - $bounding_box[2]) / 2);

$graphImage->stroke();

$image = imagecreatetruecolor(REAL_IMAGE_WIDTH, REAL_IMAGE_HEIGHT);

$white = imagecolorallocate($image, 255, 255, 255);
$black = imagecolorallocate($image, 0, 0, 0);

imagecolortransparent($image, $white);
imagefilledrectangle($image, 0, 0, REAL_IMAGE_WIDTH, REAL_IMAGE_HEIGHT, $white);
imagecopy($image, $graphImage->Picture, 0, 0, 0, 0, REAL_IMAGE_WIDTH, REAL_IMAGE_HEIGHT);
imagepng($image);
imagedestroy($image);

/**
 * Create an error image, send it to the client, and then exit
 *
 * @param $text
 */
function error_image($text) {
    // allocate image
    $image = imagecreatetruecolor(REAL_IMAGE_WIDTH, REAL_IMAGE_HEIGHT);

    // create some colours
    $white = imagecolorallocate($image, 255, 255, 255);
    $black = imagecolorallocate($image, 0, 0, 0);

    // draw teh background
    imagefilledrectangle($image, 0, 0, REAL_IMAGE_WIDTH, REAL_IMAGE_HEIGHT, $white);

    // write the text
    imagettftext($image, 16, 0, 5, 25, $black, '../fonts/pf_arma_five.ttf', $text);

    // render and destroy the image
    imagepng($image);
    imagedestroy($image);
    exit;
}