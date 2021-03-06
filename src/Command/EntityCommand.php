<?php
/**
 * This file is part of Swoft.
 *
 * @link     https://swoft.org
 * @document https://doc.swoft.org
 * @contact  group@swoft.org
 * @license  https://github.com/swoft-cloud/swoft/blob/master/LICENSE
 */
namespace Swoft\Entity\Command;

use Swoft\Bean\BeanFactory;
use Swoft\Entity\Entity\Generator;
use Swoft\Entity\Entity\Mysql\Schema;
use Swoft\Db\Pool;
use Swoft\Db\Pool\DbPool;

use Swoft\Console\Annotation\Mapping\Command;
use Swoft\Console\Annotation\Mapping\CommandMapping;

/**
 * create database entity
 *
 * @Command(name="entity",coroutine=false)
 */
class EntityCommand
{
    /**
     * @var \Swoft\Db\Entity\Schema $schema schema对象
     */
    private $schema;

    /**
     * @var Generator $generatorEntity 实体实例
     */
    private $generatorEntity;

    /**
     * @var string $entityFilePath 实体文件路径
     */
    private $entityFilePath = '@app/Model/Entity';

    /**
     * Auto create entity by table structure
     *
     * entity:create -d[|--database] <database> --instance <instance>
     * entity:create -d[|--database] <database> [table] -instnace <instnace>
     * entity:create -d[|--database] <database> --i[|--include] <table> --instnace <instnace>
     * entity:create -d[|--database] <database> --i[|--include] <table> -instnace <instnace>
     * entity:create -d[|--database] <database> --i[|--include] <table1,table2> --instnace <instnace>
     * entity:create -d[|--database] <database> --i[|--include] <table1,table2> -e[|--exclude] <table3> --instance <instance>
     * entity:create -d[|--database] <database> --i[|--include] <table1,table2> -e[|--exclude] <table3,table4> --instance <instance>
     *
     * -d 数据库
     * --database 数据库
     * -i 指定特定的数据表，多表之间用逗号分隔
     * --include 指定特定的数据表，多表之间用逗号分隔
     * -e 排除指定的数据表，多表之间用逗号分隔
     * --exclude 排除指定的数据表，多表之间用逗号分隔
     * --remove-table-prefix 去除前缀
     * --entity-file-path 实体路径(必须在以@app开头并且在app目录下存在的目录,否则将会重定向到@app/Model/Entity)
     * --instance 设置数据库实例，默认default
     * --extends 设置模型的实体基类
     * -f 强制覆盖实体文件
     *
     *
     * @CommandMapping(name="create",usage="entity:create [-f] -d[|--database] <database> --instance <instance>")
     */
    public function create()
    {
        $database = $removeTablePrefix = '';
        $tablesEnabled = $tablesDisabled = [];
        $force=false;

        $this->parseEntityFilePath();
        $this->parseDatabaseCommand($database);
        $this->parseInstanceCommand($instance);
        $this->parseEnableTablesCommand($tablesEnabled);
        $this->parseDisableTablesCommand($tablesDisabled);
        $this->parseForceFlag($force);
        $this->parseRemoveTablePrefix($removeTablePrefix);
        $this->parseExtends($extends);

        $this->initDatabase($instance);

        if (empty($database)) {
            output()->writeln('databases doesn\'t not empty!');
        } else {
            $this->generatorEntity->db = $database;
            $this->generatorEntity->instance = $instance;
            $this->generatorEntity->tablesEnabled = $tablesEnabled;
            $this->generatorEntity->tablesDisabled = $tablesDisabled;
            $this->generatorEntity->removeTablePrefix = $removeTablePrefix;
            $this->generatorEntity->force = $force;
            if (isset($extends)) $this->generatorEntity->setExtends($extends);
            $this->generatorEntity->execute($this->schema);
        }
    }

    /**
     * 初始化方法
     */
    private function initDatabase($instance = Pool::DEFAULT_POOL): bool
    {
        $instance = $instance ?? Pool::DEFAULT_POOL;
	    /**
	     * @var Pool $pool
	     */
        $pool=BeanFactory::getBean($instance);

        $schema = new Schema();
        $schema->setDriver('MYSQL');
        $this->schema = $schema;
        $this->generatorEntity = new Generator($pool->createConnection());

        return true;
    }

    /**
     * 设置实体生成路径
     */
    private function setEntityFilePath()
    {
	    \Swoft::setAlias('@entityPath', $this->entityFilePath);
    }

    /**
     * 解析需要扫描的数据库
     *
     * @param string &$database 需要扫描的数据库
     */
    private function parseDatabaseCommand(string &$database)
    {
        if (input()->hasSOpt('d') || input()->hasLOpt('database')) {
	        $database = \input()->getSameOpt(['d', 'database']);
	        if (!is_string($database)) {
		        $database=null;
	        }
        }
    }

    /**
     * 解析需要指定实例的数据库别名
     * @param string &$instance 指定实现的数据库别名
     */
    private function parseInstanceCommand(&$instance)
    {
        if (input()->hasLOpt('instance')) {
            $instance = (string)\input()->getSameOpt(['instance']);
        }
    }

    /**
     * 解析需要扫描的table
     * @param array &$tablesEnabled 需要扫描的表
     */
    private function parseEnableTablesCommand(&$tablesEnabled)
    {
        if (input()->hasSOpt('i') || input()->hasLOpt('include')) {
            $tablesEnabled = input()->hasSOpt('i') ? input()->getShortOpt('i') : input()->getLongOpt('include');
            $tablesEnabled = !empty($tablesEnabled) ? explode(',', $tablesEnabled) : [];
        }

        // 参数优先级大于选项
        if (!empty(input()->getArg(0))) {
            $tablesEnabled = [input()->getArg(0)];
        }
    }

    /**
     * 解析不需要扫描的table
     *
     * @param array &$tablesDisabled 不需要扫描的表
     */
    private function parseDisableTablesCommand(&$tablesDisabled)
    {
        if (input()->hasSOpt('e') || input()->hasLOpt('exclude')) {
            $tablesDisabled = input()->hasSOpt('e') ? input()->getShortOpt('e') : input()->getLongOpt('exclude');
            $tablesDisabled = !empty($tablesDisabled) ? explode(',', $tablesDisabled) : [];
        }
    }

    /**
     * 是否强制覆盖已经生成的实体
     *
     * @param bool &$force 表示
     */
    private function parseForceFlag(&$force)
    {
	    $force=input()->hasOpt('f');
    }

    /**
     * 移除表前缀
     *
     * @param string &$removeTablePrefix 需要移除的前缀
     */
    private function parseRemoveTablePrefix(&$removeTablePrefix)
    {
        if (input()->hasLOpt('remove-table-prefix')) {
            $removeTablePrefix = (string)input()->getLongOpt('remove-table-prefix');
        }
    }

    /**
     * 实体生成路径
     */
    private function parseEntityFilePath()
    {
        if (input()->hasLOpt('entity-file-path')) {
            $entityFilePath = (string)input()->getLongOpt('entity-file-path');
            if (preg_match('/^@app(.*)/', $entityFilePath) && is_dir(alias($entityFilePath))) {
                $this->entityFilePath = $entityFilePath;
            } else {
                output()->writeln('The directory does not exist, and the entity generated directory will be reset: ' . $this->entityFilePath);
            }
        }

        $this->setEntityFilePath();
    }

    /**
     * 实体基类
     *
     * @param string &$extends 实体基类
     */
    private function parseExtends(&$extends)
    {
        if (input()->hasLOpt('extends')) {
            $extends = (string)input()->getLongOpt('extends');
        }
    }
}
