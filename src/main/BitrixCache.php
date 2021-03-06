<?php

namespace WebArch\BitrixCache;

use Bitrix\Main\Application;
use Bitrix\Main\Data\Cache as BitrixMainDataCache;
use Exception;
use ReflectionFunction;

class BitrixCache
{
    /**
     * @var BitrixMainDataCache
     */
    private $cache;

    /**
     * @var int
     */
    private $time = 0;

    /**
     * @var string
     */
    private $id = '';

    /**
     * @var string
     */
    private $path = '';

    /**
     * @var string
     */
    private $baseDir = 'cache';

    /**
     * @var array
     */
    private $tags = [];

    /**
     * @var bool
     */
    private $clearCache = false;

    /**
     * @var callable
     */
    private $callback;

    /**
     * Передача callback, результат выполнения которого должен быть закеширован.
     *
     * @param callable $callback Если callback возвращает null, записи кеша не будет.
     *
     * @return array Если callback возвращает не array, то будет возвращён array вида ['result' => $callbackResult]
     * @throws Exception
     *
     */
    public function resultOf(callable $callback)
    {
        $this->callback = $callback;
        $this->setDefaultParams();

        if ($this->isClearCache()) {
            $this->getCache()->clean($this->getId(), $this->getPath(), $this->getBaseDir());
        }

        return $this->execute();
    }

    /**
     * @return void
     */
    protected function setDefaultParams()
    {
        if ($this->getTime() == 0) {
            $this->withTime(3600);
        }

        if (trim($this->getId()) == '') {
            $ref = new ReflectionFunction($this->callback);
            $this->withId(md5($ref->getFileName() . $ref->getStartLine() . $ref->getEndLine()));
        }

        if (trim($this->getPath()) == '') {
            $this->withPath('/');
        }
    }

    /**
     * @return array
     * @throws Exception
     */
    private function execute()
    {
        if (
            $this->getCache()->startDataCache($this->getTime(), $this->getId(), $this->getPath(), [], $this->getBaseDir())
            || $this->isClearCache()
        ) {
            $this->startTagCache();

            try {

                $result = ($this->callback)();

            } catch (Exception $exception) {

                $this->abortCache();
                throw $exception;

            }

            if (is_null($result)) {
                $this->abortCache();
                return ['result' => $result];
            }

            if (!is_array($result)) {
                $result = ['result' => $result];
            }

            $this->getCache()->endDataCache($result);
            $this->endTagCache();

            return $result;

        } else {
            return $this->getCache()->getVars();
        }
    }

    /**
     * @return bool
     */
    public function isClearCache()
    {
        return (bool)$this->clearCache;
    }

    /**
     * @param boolean $clearCache
     *
     * @return $this
     */
    public function withClearCache($clearCache)
    {
        $this->clearCache = (bool)$clearCache;

        return $this;
    }

    /**
     * @return void
     */
    public function clearTags()
    {
        $this->tags = [];
    }

    /**
     * @param string $tag
     *
     * @return $this
     */
    public function withTag($tag)
    {
        $tag = trim($tag);
        if ($tag != '') {
            $this->tags[] = $tag;
        }

        return $this;
    }

    /**
     * @param int $iblockId
     *
     * @return $this
     */
    public function withIblockTag($iblockId)
    {
        if ($iblockId > 0) {
            $this->tags[] = 'iblock_id_' . (int)$iblockId;
        }

        return $this;
    }

    /**
     * @return int
     */
    public function getTime()
    {
        return (int)$this->time;
    }

    /**
     * @param int $time
     *
     * @return $this
     */
    public function withTime($time)
    {
        $this->time = (int)$time;

        return $this;
    }

    /**
     * @return string
     */
    public function getId()
    {
        return $this->id;
    }

    /**
     * @param string $id
     *
     * @return $this
     */
    public function withId($id)
    {
        $this->id = trim($id);

        return $this;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @param string $path
     *
     * @return $this
     */
    public function withPath($path)
    {
        $this->path = trim($path);

        return $this;
    }

    /**
     * @return string
     */
    public function getBaseDir()
    {
        return $this->baseDir;
    }

    /**
     * @param string $baseDir
     *
     * @return $this
     */
    public function withBaseDir($baseDir)
    {
        $this->baseDir = $baseDir;

        return $this;
    }

    /**
     * @return BitrixMainDataCache
     * @throws \Bitrix\Main\SystemException
     */
    public function getCache()
    {
        if (is_null($this->cache)) {
            $this->cache = Application::getInstance()->getCache();
        }

        return $this->cache;
    }

    /**
     * @return bool
     */
    public function hasTags()
    {
        return count($this->tags) > 0;
    }

    /**
     * @return void
     * @throws \Bitrix\Main\SystemException
     */
    protected function startTagCache()
    {
        if ($this->hasTags()) {
            Application::getInstance()->getTaggedCache()->startTagCache($this->getPath());

            foreach ($this->tags as $tag) {
                Application::getInstance()->getTaggedCache()->registerTag($tag);
            }
        }
    }

    /**
     * @return void
     * @throws \Bitrix\Main\SystemException
     */
    protected function endTagCache()
    {
        if ($this->hasTags()) {
            Application::getInstance()->getTaggedCache()->endTagCache();
        }
    }

    /**
     * Отменяет запись кеша.
     *
     * @return void
     * @throws \Bitrix\Main\SystemException
     */
    public function abortCache()
    {
        $this->getCache()->abortDataCache();
        if ($this->hasTags()) {
            Application::getInstance()->getTaggedCache()->abortTagCache();
        }
    }
}
