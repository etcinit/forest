<?php

namespace Chromabits\Forest\Entities;

class Branch
{
    protected $id;

    protected $acres;

    protected $tp1Vol;
    protected $tp2Vol;
    protected $tp3Vol;

    protected $neighbors = [];

    function __construct($id, $acres, $tp1Vol, $tp2Vol, $tp3Vol)
    {
        $this->acres = $acres;
        $this->id = $id;
        $this->tp1Vol = $tp1Vol;
        $this->tp2Vol = $tp2Vol;
        $this->tp3Vol = $tp3Vol;
    }

    function addNeighbor(Branch $neighbor)
    {
        $this->neighbors[$neighbor->getId()] = $neighbor;
    }

    function getId()
    {
        return $this->id;
    }

    /**
     * @return mixed
     */
    public function getAcres()
    {
        return $this->acres;
    }

    /**
     * @param mixed $acres
     */
    public function setAcres($acres)
    {
        $this->acres = $acres;
    }

    /**
     * @return array
     */
    public function getNeighbors()
    {
        return $this->neighbors;
    }

    /**
     * @param array $neighbors
     */
    public function setNeighbors($neighbors)
    {
        $this->neighbors = $neighbors;
    }

    /**
     * @return mixed
     */
    public function getTp1Vol()
    {
        return $this->tp1Vol;
    }

    /**
     * @param mixed $tp1Vol
     */
    public function setTp1Vol($tp1Vol)
    {
        $this->tp1Vol = $tp1Vol;
    }

    /**
     * @return mixed
     */
    public function getTp2Vol()
    {
        return $this->tp2Vol;
    }

    /**
     * @param mixed $tp2Vol
     */
    public function setTp2Vol($tp2Vol)
    {
        $this->tp2Vol = $tp2Vol;
    }

    /**
     * @return mixed
     */
    public function getTp3Vol()
    {
        return $this->tp3Vol;
    }

    /**
     * @param mixed $tp3Vol
     */
    public function setTp3Vol($tp3Vol)
    {
        $this->tp3Vol = $tp3Vol;
    }


} 