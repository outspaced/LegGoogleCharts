<?php

/*
 * This file is part of the LegGCharts package.
 *
 * (c) Titouan Galopin <http://titouangalopin.com/>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

namespace Leg\GCharts;

use Leg\GCharts\DataSet\DataSet;
use Leg\GCharts\DataSet\DataSetCollection;

class BaseChart implements ChartInterface
{
	/**
	 * @var string
	 */
	const BASE_URL = 'http://chart.googleapis.com/chart';

	/**
	 * @var string
	 */
	protected $type;

	/**
	 * @var integer
	 */
	protected $width;

	/**
	 * @var integer
	 */
	protected $height;

	/**
	 * @var DataSetCollection
	 */
	protected $datas;

	/**
	 * @var DataSet
	 */
	protected $labels;

	/**
	 * @var DataSet
	 */
	protected $labels_options;

	/**
	 * @var DataSet
	 */
	protected $colors;

	/**
	 * @var string
	 */
	protected $title;

	/**
	 * @var DataSet
	 */
	protected $title_options;

	/**
	 * @var boolean
	 */
	protected $transparency;

	/**
	 * @var DataSet
	 */
	protected $margins;

	/**
	 * @var DataSet
	 */
	protected $fill;

	/**
	 * @var array
	 */
	protected $line_fill;

	/**
	 * @var string
	 */
	protected $custom_scaling;
	
	/**
	 * @var string
	 */
	protected $visible_axis;
	
	/**
	 * @var string
	 */
	protected $axis_label_styles;
	
	/**
	 * @var string
	 */
	protected $chart_legend_position;

	/**
	 * @var array
	 */
	protected $axis_tick_mark_styles;
	
	/**
	 * Constructor
	 */
	public function __construct()
	{
		$this->datas = new DataSetCollection();
		$this->labels = new DataSet();
		$this->labels_options = new DataSet();
		$this->colors = new DataSet();
		$this->title_options = new DataSet();
		$this->fill = new DataSet();
		
		$this->margins = new DataSet(array(
			'top' => null,
			'bottom' => null,
			'left' => null,
			'right' => null,
			'legend-width' => null,
			'legend-height' => null
		));

		$this->setOptions($this->getDefaultOptions());
	}

	/**
	 * Set options
	 *
	 * @param array $options
	 * @throws \InvalidArgumentException
	 */
	public function setOptions(array $options)
	{
		foreach ($options as $option => $value) {
			$funcName = array_map('ucfirst', explode('_', $option));
			$funcName = 'set'.implode('', $funcName);

			if (method_exists($this, $funcName)) {
				$this->$funcName($value);
			} else {
				throw new \InvalidArgumentException(sprintf(
					'Unknown chart option "%s" or chart method "%s()"',
					$option, $funcName
				), 500);
			}
		}
	}

	/**
	 * @return array
	 */
	public function getDefaultOptions()
	{
		return array();
	}

	/**
	 * Build and return URI
	 *
	 * @return string
	 * @throws \InvalidArgumentException
	 */
	public function build()
	{
		if (empty($this->type)) {
			throw new \InvalidArgumentException('A chart must have a type.', 500);
		}

		if ($this->datas->isEmpty()) {
			throw new \InvalidArgumentException('A chart must have datas.', 500);
		}

		if (empty($this->width)) {
			throw new \InvalidArgumentException('A chart must have a width.', 500);
		}

		if (empty($this->height)) {
			throw new \InvalidArgumentException('A chart must have a height.', 500);
		}
		
		$url = self::BASE_URL.'?cht='.$this->type;
		$url .= '&chs='.$this->width.'x'.$this->height;

		if ($this->getLineStyle()) {
			$url .= '&chls=' . $this->convertMultiDimensionalToString($this->getLineStyle());
		}
		
		
		$dataSets = array();

		foreach ($this->datas->toArray() as $dataSet) {
			$dataSets[] = implode(',', $dataSet->toArray());
		}

		$url .= '&chd=t:'.implode('|', $dataSets);

		if (! $this->colors->isEmpty()) {
			$colorsSeparator = ($this->type == 'lc') ? ',' : '|'; 
			$url .= '&chco='.implode($colorsSeparator, $this->colors->toArray());
		}

		// Fill
		if ($this->isTransparent()) {
			$url .= '&chf=bg,s,65432100';
		} elseif (! $this->fill->isEmpty()) {
			switch($this->fill->get('type')) {
				case 'chart':
					$fill_type = 'c';
				break;
				case 'background':
				default:
					$fill_type = 'bg';
				break;
			}
			
			$url .= '&chf='.$fill_type.',s,'.$this->fill->get('color');
		}
		
		// Line fill!
		if ($this->getLineFill()) {
			$url .= '&chm=' . $this->convertMultiDimensionalToString($this->getLineFill());
		}
		
		if (! $this->labels->isEmpty()) {
			$url .= '&chl='.implode('|', $this->labels->toArray());

			if ($this->labels_options->get('position')) {
				$url .= '&chdlp='.$this->labels_options->get('position');
			}

			if ($this->labels_options->get('color')) {
				$url .= '&chdls='.$this->labels_options->get('color');
			}

			if ($this->labels_options->get('font-size')) {
				if ($this->labels_options->get('color')) {
					$url .= ','.$this->labels_options->get('font-size');
				} else {
					$url .= '&chdls=,'.$this->labels_options->get('font-size');
				}
			}
		}

		if (! empty($this->title)) {
			$url .= '&chtt='.urlencode($this->title);

			// Check color
			if ($this->title_options->get('color')) {
				$url .= '&chts='.$this->title_options->get('color');
			}

			// Check font size
			if ($this->title_options->get('font-size')) {
				if ($this->title_options->get('color')) {
					$url .= ','.$this->title_options->get('font-size');
				} else {
					$url .= '&chts=,'.$this->title_options->get('font-size');
				}
			}

			// Check alignement
			if ($this->title_options->get('text-align')) {
				if ($this->title_options->get('color') OR $this->title_options->get('font-size')) {
					$url .= ','.$this->title_options->get('text-align');
				} else {
					$url .= '&chts=,,'.$this->title_options->get('text-align');
				}
			}
		}

		// Margins
		$margins = $this->margins;

		if ($margins->get('top') || $margins->get('bottom') || $margins->get('left')
			|| $margins->get('right') || $margins->get('legend-width') || $margins->get('legend-height')) {

			$url .= '&chma='.((float) $margins->get('top')).',';
			$url .= ((float) $margins->get('bottom')).',';
			$url .= ((float) $margins->get('left')).',';
			$url .= ((float) $margins->get('right')).',';
			$url .= ((float) $margins->get('legend-width')).',';
			$url .= ((float) $margins->get('legend-height'));
		}
		
		if ($this->getCustomScaling()) {
			$url .= '&chds='.$this->getCustomScaling();
		}	
	
		if ($this->getVisibleAxis()) {
			$url .= '&chxt='.$this->getVisibleAxis();
		}	
	
		if ($this->getAxisLabelStyles()) {
			$url .= '&chxs='.$this->getAxisLabelStyles();
		}	
	
		if ($this->getChartLegendPosition()) {
			$url .= '&chdlp='.$this->getChartLegendPosition();
		}	
		
		if ($this->getAxisTickMarkStyle()) {
			$url .= '&chxtc=' . $this->convertMultiDimensionalToString($this->getAxisTickMarkStyle());
		}
			
		return $url;
	}
	
	
	/**
	 * Takes a 2-level array and turns it into a string where bottom-level elements are comma-separated, and top
	 *	 level is pipe-separated
	 *	 
	 * @param  array $values
	 * @return string
	 */
	protected function convertMultiDimensionalToString(array $values)
	{
		$strings = [];
		
		foreach($values as $value) {
			if ($value instanceof DataSet) {
				$imploder = $value->toArray();
			} else {
				$imploder = $value;
			}
			
			$strings[] = implode(',', $imploder);
		}
			
		return implode('|', $strings);
	}

	/**
	 * @return string
	 */
	public function getType()
	{
		return $this->type;
	}

	/**
	 * @param string $type
	 * @return $this
	 */
	public function setType($type)
	{
		$this->type = (string) $type;

		return $this;
	}

	/**
	 * @return integer
	 */
	public function getWidth()
	{
		return $this->width;
	}

	/**
	 * @param int $width
	 * @return $this
	 */
	public function setWidth($width)
	{
		$this->width = (int) $width;

		return $this;
	}

	/**
	 * @return integer
	 */
	public function getHeight()
	{
		return $this->height;
	}

	/**
	 * @param int $height
	 * @return $this
	 */
	public function setHeight($height)
	{
		$this->height = (int) $height;

		return $this;
	}

	/**
	 * Register data sets
	 *
	 * @param array $1
	 * @param array $2
	 * @param array $...
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function setDatas()
	{
		foreach (func_get_args() as $datas) {
			foreach ($datas as $data) {
				if (! is_numeric($data)) {
					throw new \InvalidArgumentException(sprintf(
						'Datas must be numbers (%s given)', gettype($data)
					), 500);
				}
			}

			$this->datas->add(new DataSet($datas));
		}

		return $this;
	}

	/**
	 * @return DataSet
	 */
	public function getDatas()
	{
		return $this->datas;
	}

	/**
	 * @param array $labels
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function setLabels(array $labels)
	{
		foreach ($labels as $label) {
			if (! is_numeric($label) && ! is_string($label)) {
				throw new \InvalidArgumentException(sprintf(
					'Labels must be numbers or strings (%s given)', gettype($label)
				), 500);
			}
		}

		$this->labels = new DataSet($labels);

		return $this;
	}

	/**
	 * @return DataSet
	 */
	public function getLabels()
	{
		return $this->labels;
	}

	/**
	 * @param array $labels_options
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function setLabelsOptions(array $labels_options)
	{
		foreach($labels_options as $option => $value) {
			switch($option) {
				case 'position':
					if (! is_string($value)) {
						throw new \InvalidArgumentException(sprintf(
							'The label position must be a string (%s given)', gettype($value)
						), 500);
					}

					if (! in_array($value, array('b', 'bv', 't', 'tv', 'r', 'l'))) {
						throw new \InvalidArgumentException(sprintf(
							'Unknown label position "%s". Valid positions are : b, bv, t, tv, r, l.',
							$value
						), 500);
					}
				break;

				case 'color':
					if (! is_string($value)) {
						throw new \InvalidArgumentException(sprintf(
							'The label color must be a string (%s given)', gettype($value)
						), 500);
					}

					if (! preg_match('#^[a-z0-9]{6,8}$#i', $value)) {
						throw new \InvalidArgumentException(sprintf(
							'The label color must be a hexadecimal value ("%s" given).',
							$value
						), 500);
					}
				break;

				case 'font-size':
					if (! is_numeric($value)) {
						throw new \InvalidArgumentException(sprintf(
							'The label font size must be numeric (%s given).',
							gettype($value)
						), 500);
					}
				break;

				default:
					throw new \InvalidArgumentException(sprintf(
						'Unknown label option "%s". Valid options are : position, color, font-size.',
						$option
					), 500);
			}
		}

		$this->labels_options = new DataSet($labels_options);

		return $this;
	}

	/**
	 * @return DataSet
	 */
	public function getLabelsOptions()
	{
		return $this->labels_options;
	}

	/**
	 * @param array $colors
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function setColors(array $colors)
	{
		foreach ($colors as $color) {
			if (! is_string($color)) {
				throw new \InvalidArgumentException(sprintf(
					'A color must be a string (%s given).',
					gettype($color)
				), 500);
			}

			if (! preg_match('#^[a-z0-9]{6}$#i', $color)) {
				throw new \InvalidArgumentException(sprintf(
					'A color must be a hexadecimal string ("%s" given).',
					$color
				), 500);
			}
		}

		$this->colors = new DataSet($colors);

		return $this;
	}

	/**
	 * @return DataSet
	 */
	public function getColors()
	{
		return $this->colors;
	}

	/**
	 * @param string $title
	 * @return $this
	 */
	public function setTitle($title)
	{
		$this->title = (string) $title;

		return $this;
	}

	/**
	 * @return string
	 */
	public function getTitle()
	{
		return $this->title;
	}

	/**
	 * @param array $title_options
	 * @return $this
	 * @throws \InvalidArgumentException
	 */
	public function setTitleOptions(array $title_options)
	{
		foreach ($title_options as $option => $value) {
			switch ($option) {
				case 'text-align':
					if (! is_string($value)) {
						throw new \InvalidArgumentException(sprintf(
							'The title position must be a string (%s given)', gettype($value)
						), 500);
					}

					if (! in_array($value, array('left', 'center', 'right'))) {
						throw new \InvalidArgumentException(sprintf(
							'Unknown title position "%s". Valid positions are : left, center, right.',
							$value
						), 500);
					}
				break;

				case 'color':
					if (! is_string($value)) {
						throw new \InvalidArgumentException(sprintf(
							'The title color must be a string (%s given)', gettype($value)
						), 500);
					}

					if (! preg_match('#^[a-z0-9]{6,8}$#i', $value)) {
						throw new \InvalidArgumentException(sprintf(
							'The title color must be a hexadecimal value ("%s" given).',
							$value
						), 500);
					}

					break;

				case 'font-size':
					if (! is_numeric($value)) {
						throw new \InvalidArgumentException(sprintf(
							'The title font size must be numeric (%s given).',
							gettype($value)
						), 500);
					}
				break;

				default:
					throw new \InvalidArgumentException(sprintf(
						'Unknown title option "%s". Valid options are : text-align, color, font-size.',
						$option
					), 500);
			}
		}

		$this->title_options = new DataSet($title_options);

		return $this;
	}

	/**
	 * @return DataSet
	 */
	public function getTitleOptions()
	{
		return $this->title_options;
	}

	/**
	 * @param bool $transparency
	 * @return $this
	 */
	public function setTransparency($transparency)
	{
		$this->transparency = (boolean) $transparency;

		return $this;
	}

	/**
	 * @return boolean
	 */
	public function isTransparent()
	{
		return $this->transparency;
	}

	/**
	 * @param array $margins
	 * @return BaseChart
	 */
	public function setMargins(array $margins)
	{
		$this->margins = new DataSet($margins);

		return $this;
	}

	/**
	 * @return DataSet
	 */
	public function getMargins()
	{
		return $this->margins;
	}
	
	/**
	 * @param array $fill
	 * @return BaseChart
	 */
	public function setFill(array $fill)
	{
		$this->fill = new DataSet($fill);

		return $this;
	}

	/**
	 * @return DataSet
	 */
	public function getFill()
	{
		return $this->fill;
	}	
	
	/**
	 * @param  array $line_fill
	 * @return BaseChart
	 */
	public function setLineFill(array $line_fills)
	{
		$this->line_fill = [];
		
		// @todo better handling for this multi-dimensionality
		foreach($line_fills as $line_fill) {
			$this->line_fill[] = new DataSet($line_fill);
		}
		
		return $this;
	}

	/**
	 * @return DataSet
	 */
	public function getLineFill()
	{
		return $this->line_fill;
	}
	
	/**
	 * @param  string $custom_scaling
	 * @return BaseChart
	 */
	public function setCustomScaling($custom_scaling)
	{
		$this->custom_scaling = $custom_scaling;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getCustomScaling()
	{
		return $this->custom_scaling;
	}	
	
	/**
	 * @param  string $visible_axis
	 * @return BaseChart
	 */
	public function setVisibleAxis($visible_axis)
	{
		$this->visible_axis = $visible_axis;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getVisibleAxis()
	{
		return $this->visible_axis;
	}	
	
	/**
	 * @param  string $axis_label_styles
	 * @return BaseChart
	 */
	public function setAxisLabelStyles($axis_label_styles)
	{
		$this->axis_label_styles = $axis_label_styles;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getAxisLabelStyles()
	{
		return $this->axis_label_styles;
	}	
	
	/**
	 * @param  string $chart_legend_position
	 * @return BaseChart
	 */
	public function setChartLegendPosition($chart_legend_position)
	{
		$this->chart_legend_position = $chart_legend_position;
		return $this;
	}

	/**
	 * @return string
	 */
	public function getChartLegendPosition()
	{
		return $this->chart_legend_position;
	}
	
	/**
	 * @param  array $axis_tick_mark_style
	 * @return BaseChart
	 */
	public function setAxisTickMarkStyle(array $axis_tick_mark_styles)
	{
		$this->axis_tick_mark_style = [];
		
		// @todo better handling for this multi-dimensionality
		foreach($axis_tick_mark_styles as $axis_tick_mark_style) {
			$this->axis_tick_mark_style[] = new DataSet($axis_tick_mark_style);
		}
		
		return $this;
	}

	/**
	 * @return array
	 */
	public function getAxisTickMarkStyle()
	{
		return $this->axis_tick_mark_style;
	}
	
	/**
	 * @param  array $line_style
	 * @return BaseChart
	 */
	public function setLineStyle(array $line_styles)
	{
		$this->line_style = [];
		
		// @todo better handling for this multi-dimensionality
		foreach($line_styles as $line_style) {
			$this->line_style[] = new DataSet($line_style);
		}
		
		return $this;
	}

	/**
	 * @return array
	 */
	public function getLineStyle()
	{
		return $this->line_style;
	}
	
}
