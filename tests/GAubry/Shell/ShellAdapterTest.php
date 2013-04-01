<?php

namespace GAubry\Logger\Tests;

use \GAubry\Shell\ShellAdapter;
use \GAubry\Logger\MinimalLogger;
use \Psr\Log\LogLevel;

/**
 * @category TwengaDeploy
 * @package Tests
 * @author Geoffroy AUBRY <geoffroy.aubry@twenga.com>
 */
class ShellAdapterTest extends \PHPUnit_Framework_TestCase
{

    /**
     * Instance de log.
     * @var Logger_Interface
     */
    private $oLogger;

    /**
     * Instance Shell.
     * @var Shell_Interface
     */
    private $oShell;

    private $_aConfig;

    /**
     * Tableau indexé contenant les commandes Shell de tous les appels effectués à Shell_Adapter::exec().
     * @var array
     * @see shellExecCallback()
     */
    private $aShellExecCmds;

    /**
     * Callback déclenchée sur appel de Shell_Adapter::exec().
     * Log tous les appels dans le tableau indexé $this->aShellExecCmds.
     *
     * @param string $sCmd commande Shell qui aurait dûe être exécutée.
     * @return array tableau vide
     * @see $aShellExecCmds
     */
    public function shellExecCallback ($sCmd)
    {
        $this->aShellExecCmds[] = $sCmd;
        return array();
    }

    /**
     * Sets up the fixture, for example, open a network connection.
     * This method is called before a test is executed.
     */
    public function setUp ()
    {
        $this->_aConfig = $GLOBALS['aConfig']['GAubry\Shell'];
        $this->oLogger = new MinimalLogger(LogLevel::WARNING);
        $this->oShell = new ShellAdapter($this->oLogger, $this->_aConfig);
    }

    /**
     * Tears down the fixture, for example, close a network connection.
     * This method is called after a test is executed.
     */
    public function tearDown()
    {
        $this->oLogger = NULL;
        $this->oShell = NULL;
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::isRemotePath
     */
    public function testIsRemotePath_WithEmptyPath ()
    {
        $this->assertEquals(array(false, '', ''), $this->oShell->isRemotePath(''));
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::isRemotePath
     */
    public function testIsRemotePath_WithLocalPath ()
    {
        $this->assertEquals(
            array(false, '', '/path/to/my file'),
            $this->oShell->isRemotePath('/path/to/my file')
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::isRemotePath
     */
    public function testIsRemotePath_WithRemotePathWithoutLogin ()
    {
        $this->assertEquals(
            array(true, 'dv2', '/path/to/my file'),
            $this->oShell->isRemotePath('dv2:/path/to/my file')
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::isRemotePath
     */
    public function testIsRemotePath_WithRemotePathWithLogin ()
    {
        $this->assertEquals(
            array(true, 'gaubry@dv2', '/path/to/my file'),
            $this->oShell->isRemotePath('gaubry@dv2:/path/to/my file')
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::isRemotePath
     */
    public function testIsRemotePath_WithParameterInUser ()
    {
        $this->assertEquals(
            array(true, '${P}@dv2', '/path/to/my file'),
            $this->oShell->isRemotePath('${P}@dv2:/path/to/my file')
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::isRemotePath
     */
    public function testIsRemotePath_WithParameterInServerWithUser ()
    {
        $this->assertEquals(
            array(true, 'user@${P}', '/path/to/my file'),
            $this->oShell->isRemotePath('user@${P}:/path/to/my file')
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::isRemotePath
     */
    public function testIsRemotePath_WithParameterInServerWithoutUser ()
    {
        $this->assertEquals(
            array(true, '${P}', '/path/to/my file'),
            $this->oShell->isRemotePath('${P}:/path/to/my file')
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::isRemotePath
     */
    public function testIsRemotePath_WithParameterInPathWithServer ()
    {
        $this->assertEquals(
            array(true, '${P}', '/path/${Q}/my file'),
            $this->oShell->isRemotePath('${P}:/path/${Q}/my file')
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::isRemotePath
     */
    public function testIsRemotePath_WithParameterInPathWithoutServer ()
    {
        $this->assertEquals(
            array(false, '', '/path/${P}/my file'),
            $this->oShell->isRemotePath('/path/${P}/my file')
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::escapePath
     */
    public function testEscapePath_WithEmptyPath ()
    {
        $this->assertEquals('', $this->oShell->escapePath(''));
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::escapePath
     */
    public function testEscapePath_WithSimplePath ()
    {
        $this->assertEquals('"/path/to/my file"', $this->oShell->escapePath('/path/to/my file'));
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::escapePath
     */
    public function testEscapePath_WithJokersPath ()
    {
        $this->assertEquals('"/a/b"?"/img"*"jpg"', $this->oShell->escapePath('/a/b?/img*jpg'));
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::escapePath
     */
    public function testEscapePath_WithConsecutiveJokersPath ()
    {
        $this->assertEquals('"/a/b/img"?*"jpg"', $this->oShell->escapePath('/a/b/img?*jpg'));
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::escapePath
     */
    public function testEscapePath_WithBoundJokersPath ()
    {
        $this->assertEquals('?"/a/b/img"*', $this->oShell->escapePath('?/a/b/img*'));
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::parallelize
     */
    public function testParallelize_wellFormedSimpleCall ()
    {
        $sExpectedCmd = $this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir']
                      . '/parallelize.inc.sh "aai@aai-01 prod@aai-01"'
                      . ' "ssh [] ' . $this->_aConfig['bash_path']
                      . ' <<EOF' . "\n" . 'ls -l' . "\n" . 'EOF' . "\n" . '"';
        $aReturnExec = array(
            '---[aai@aai-01]-->0|0s', '[CMD]', 'foo', '[OUT]', '1', '[ERR]', '///',
            '---[prod@aai-01]-->0|0s', '[CMD]', 'foo', '[OUT]', '0', '[ERR]', '///',
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));

        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo($sExpectedCmd))
            ->will($this->returnValue($aReturnExec));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $oMockShell->parallelize(
            array('aai@aai-01', 'prod@aai-01'),
            "ssh [] /bin/bash <<EOF\nls -l\nEOF\n",
            2
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::parallelize
     */
    public function testParallelize_wellFormedSplittedCalls ()
    {
        $sFirstExpectedCmd = $this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir']
                      . '/parallelize.inc.sh "aai@aai-01 prod@aai-01 x"'
                      . ' "ssh [] ' . $this->_aConfig['bash_path']
                      . ' <<EOF' . "\n" . 'ls -l' . "\n" . 'EOF' . "\n" . '"';
        $aFirstReturnExec = array(
            '---[aai@aai-01]-->0|0s', '[CMD]', 'foo', '[OUT]', '1', '[ERR]', '///',
            '---[prod@aai-01]-->0|0s', '[CMD]', 'foo', '[OUT]', '0', '[ERR]', '///',
            '---[x]-->0|0s', '[CMD]', 'foo', '[OUT]', '0', '[ERR]', '///',
        );
        $sSecondExpectedCmd = $this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir']
                      . '/parallelize.inc.sh "y z"'
                      . ' "ssh [] ' . $this->_aConfig['bash_path']
                      . ' <<EOF' . "\n" . 'ls -l' . "\n" . 'EOF' . "\n" . '"';
        $aSecondReturnExec = array(
            '---[y]-->0|0s', '[CMD]', 'foo', '[OUT]', '1', '[ERR]', '///',
            '---[z]-->0|0s', '[CMD]', 'foo', '[OUT]', '0', '[ERR]', '///',
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));

        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo($sFirstExpectedCmd))
            ->will($this->returnValue($aFirstReturnExec));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($sSecondExpectedCmd))
            ->will($this->returnValue($aSecondReturnExec));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $oMockShell->parallelize(
            array('aai@aai-01', 'prod@aai-01', 'x', 'y', 'z'),
            "ssh [] /bin/bash <<EOF\nls -l\nEOF\n",
            3
        );
    }

    /*
     * @covers \GAubry\Shell\ShellAdapter::parallelize
     */
    public function testParallelize_ThrowExceptionWhenExecFailed ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->exactly(1))->method('exec');
        $oMockShell->expects($this->at(0))->method('exec')->will($this->throwException(new \RuntimeException('aborted!')));
        $this->setExpectedException('RuntimeException', 'aborted!');
        $oMockShell->parallelize(
            array('a', 'b'),
            'cat ' . $this->_aConfig['tests_resources_dir'] . '/testParallelize_[].txt',
            2
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::parallelize
     */
    public function testParallelize_simple ()
    {
        $aExpectedResult = array(
            array(
                'value' => 'a',
                'error_code' => 0,
                //'elapsed_time' => 0,
                'cmd' => 'cat ' . $this->_aConfig['tests_resources_dir'] . '/testParallelize_a.txt',
                'output' => file_get_contents($this->_aConfig['tests_resources_dir'] . '/testParallelize_a.txt'),
                'error' => ''
            ),
            array(
                'value' => 'b',
                'error_code' => 0,
                //'elapsed_time' => 0,
                'cmd' => 'cat ' . $this->_aConfig['tests_resources_dir'] . '/testParallelize_b.txt',
                'output' => file_get_contents($this->_aConfig['tests_resources_dir'] . '/testParallelize_b.txt'),
                'error' => ''
            ),
        );

        $aResult = $this->oShell->parallelize(
            array('a', 'b'),
            'cat ' . $this->_aConfig['tests_resources_dir'] . '/testParallelize_[].txt',
            2
        );
        unset($aResult[0]['elapsed_time']);
        unset($aResult[1]['elapsed_time']);

        $this->assertEquals($aExpectedResult, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::parallelize
     */
    public function testParallelize_splitted ()
    {
        $aExpectedResult = array(
            array(
                'value' => 'a',
                'error_code' => 0,
                //'elapsed_time' => 0,
                'cmd' => 'cat ' . $this->_aConfig['tests_resources_dir'] . '/testParallelize_a.txt',
                'output' => file_get_contents($this->_aConfig['tests_resources_dir'] . '/testParallelize_a.txt'),
                'error' => ''
            ),
            array(
                'value' => 'b',
                'error_code' => 0,
                //'elapsed_time' => 0,
                'cmd' => 'cat ' . $this->_aConfig['tests_resources_dir'] . '/testParallelize_b.txt',
                'output' => file_get_contents($this->_aConfig['tests_resources_dir'] . '/testParallelize_b.txt'),
                'error' => ''
            ),
            array(
                'value' => 'c',
                'error_code' => 0,
                //'elapsed_time' => 0,
                'cmd' => 'cat ' . $this->_aConfig['tests_resources_dir'] . '/testParallelize_c.txt',
                'output' => file_get_contents($this->_aConfig['tests_resources_dir'] . '/testParallelize_c.txt'),
                'error' => ''
            ),
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')->will($this->returnCallback(array($this->oShell, 'exec')));
        $oMockShell->expects($this->at(1))->method('exec')->will($this->returnCallback(array($this->oShell, 'exec')));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->parallelize(
            array('a', 'b', 'c'),
            'cat ' . $this->_aConfig['tests_resources_dir'] . '/testParallelize_[].txt',
            2
        );
        unset($aResult[0]['elapsed_time']);
        unset($aResult[1]['elapsed_time']);
        unset($aResult[2]['elapsed_time']);

        $this->assertEquals($aExpectedResult, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::parallelize
     */
    public function testParallelize_ThrowExceptionOnShellExitCodeNotNull ()
    {
        $this->setExpectedException(
            'RuntimeException',
            'cat: ' . $this->_aConfig['tests_resources_dir'] . '/not_exists.txt: No such file or directory'
        );
        $aResult = $this->oShell->parallelize(
            array('testParallelize_a', 'not_exists'),
            'cat ' . $this->_aConfig['tests_resources_dir'] . '/[].txt',
            2
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::parallelize
     */
    public function testParallelize_ThrowExceptionWhenNotAskedValue ()
    {
        $this->setExpectedException(
            'RuntimeException',
            "Not asked value: 'not_asked'!"
        );
        $aReturnExec = array('---[not_asked]-->0|0s', '[CMD]', 'foo', '[OUT]', '1', '[ERR]', '///');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->will($this->returnValue($aReturnExec));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $oMockShell->parallelize(
            array('a'),
            'cat ' . $this->_aConfig['tests_resources_dir'] . '/[].txt',
            2
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::parallelize
     */
    public function testParallelize_ThrowExceptionWhenMissingValues ()
    {
        $this->setExpectedException(
            'RuntimeException',
            'Missing values!'
        );
        $aReturnExec = array('---[a]-->0|0s', '[CMD]', 'foo', '[OUT]', '1', '[ERR]', '///');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->will($this->returnValue($aReturnExec));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $oMockShell->parallelize(
            array('a', 'b'),
            'cat ' . $this->_aConfig['tests_resources_dir'] . '/[].txt',
            2
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::exec
     */
    public function testExec_ThrowExceptionOnShellError ()
    {
        $this->setExpectedException('RuntimeException', "abc\ndef", 101);
        $aResult = $this->oShell->exec('echo abc; echo def; exit 101');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::exec
     */
    public function testExec_OneLineResult ()
    {
        $aResult = $this->oShell->exec('echo abc');
        $this->assertEquals(array('abc'), $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::exec
     */
    public function testExec_MultiLineResult ()
    {
        $aResult = $this->oShell->exec('echo abc; echo def');
        $this->assertEquals(array('abc', 'def'), $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::exec
     */
    public function testExec_ErrorMultiLineResult ()
    {
        $aResult = $this->oShell->exec('(echo abc; echo def) >&2');
        $this->assertEquals(array('abc', 'def'), $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::execSSH
     */
    public function testExecSsh_ThrowExceptionWhenExecFailed ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->exactly(1))->method('exec');
        $oMockShell->expects($this->at(0))->method('exec')->will($this->throwException(new \RuntimeException('aborted!')));
        $this->setExpectedException('RuntimeException', 'aborted!');
        $oMockShell->execSSH('foo', 'bar');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::execSSH
     */
    public function testExecSsh_WithLocalPath ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('ls "/path/to/my file"'))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->execSSH('ls %s', '/path/to/my file');
        $this->assertEquals($aExpectedResult, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::execSSH
     */
    public function testExecSsh_WithMultipleLocalPath ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('ls "/path/to/my file"; cd "/path/to/my file"'))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->execSSH('ls %1$s; cd %1$s', '/path/to/my file');
        $this->assertEquals($aExpectedResult, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::execSSH
     */
    public function testExecSsh_WithRemotePath ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T gaubry@dv2 /bin/bash <<EOF' . "\n" . 'ls "/path/to/my file"' . "\n" . 'EOF' . "\n"))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->execSSH('ls %s', 'gaubry@dv2:/path/to/my file');
        $this->assertEquals($aExpectedResult, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::mkdir
     */
    public function testMkdir_ThrowExceptionWhenExecFailed ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->exactly(1))->method('exec');
        $oMockShell->expects($this->at(0))->method('exec')->will($this->throwException(new \RuntimeException('aborted!')));
        $this->setExpectedException('RuntimeException', 'aborted!');
        $oMockShell->mkdir('foo');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::mkdir
     */
    public function testMkdir_WithLocalPath ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "/path/to/my file"'))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->mkdir('/path/to/my file');
        $this->assertEquals($aExpectedResult, $aResult);
        $this->assertAttributeEquals(array('/path/to/my file' => 2), '_aFileStatus', $oMockShell);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::mkdir
     */
    public function testMkdir_WithLocalPathAndMode ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "/path/to/my file" && chmod 777 "/path/to/my file"'));
        $oMockShell->expects($this->exactly(1))->method('exec');
        $aResult = $oMockShell->mkdir('/path/to/my file', '777');
        $this->assertAttributeEquals(array('/path/to/my file' => 2), '_aFileStatus', $oMockShell);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::mkdir
     */
    public function testMkdir_WithRemotePath ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T gaubry@dv2 /bin/bash <<EOF' . "\n" . 'mkdir -p "/path/to/my file"' . "\n" . 'EOF' . "\n"))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->mkdir('gaubry@dv2:/path/to/my file');
        $this->assertEquals($aExpectedResult, $aResult);
        $this->assertAttributeEquals(array('gaubry@dv2:/path/to/my file' => 2), '_aFileStatus', $oMockShell);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::mkdir
     */
    public function testMkdir_WithRemotePathAndMode ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T gaubry@dv2 /bin/bash <<EOF' . "\n" . 'mkdir -p "/path/to/my file" && chmod 777 "/path/to/my file"' . "\n" . 'EOF' . "\n"));
        $oMockShell->expects($this->exactly(1))->method('exec');
        $aResult = $oMockShell->mkdir('gaubry@dv2:/path/to/my file', '777');
        $this->assertAttributeEquals(array('gaubry@dv2:/path/to/my file' => 2), '_aFileStatus', $oMockShell);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::remove
     */
    public function testRemove_ThrowExceptionWhenExecFailed ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->exactly(1))->method('exec');
        $oMockShell->expects($this->at(0))->method('exec')->will($this->throwException(new \RuntimeException('aborted!')));
        $this->setExpectedException('RuntimeException', 'aborted!');
        $oMockShell->remove('foo/bar');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::remove
     */
    public function testRemove_ThrowExceptionWhenTooShortPath ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $this->setExpectedException('DomainException', "Illegal path: 'foo'");
        $oMockShell->remove('foo');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::remove
     */
    public function testRemove_WithLocalPath ()
    {
        $aExpectedResult = array('blabla');
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));

        $oClass = new \ReflectionClass('\GAubry\Shell\ShellAdapter');
        $oProperty = $oClass->getProperty('_aFileStatus');
        $oProperty->setAccessible(true);
        $oProperty->setValue($oMockShell, array(
            '/path/to/my file/subdir1' => 1,
            '/path/to/a' => 2,
            '/path/to/my file/sub/subdir2' => 1,
            '/path/to/b' => 1,
            '/path/to/my file' => 1,
        ));

        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('rm -rf "/path/to/my file"'))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->remove('/path/to/my file');
        $this->assertEquals($aExpectedResult, $aResult);

        $this->assertAttributeEquals(array(
            '/path/to/a' => 2,
            '/path/to/b' => 1,
        ), '_aFileStatus', $oMockShell);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::remove
     */
    public function testRemove_WithRemotePath ()
    {
        $aExpectedResult = array('blabla');
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));

        $oClass = new \ReflectionClass('\GAubry\Shell\ShellAdapter');
        $oProperty = $oClass->getProperty('_aFileStatus');
        $oProperty->setAccessible(true);
        $oProperty->setValue($oMockShell, array(
            '/path/to/my file/subdir1' => 1,
            '/path/to/a' => 2,
            'gaubry@dv2:/path/to/my file/sub/subdir2' => 1,
            '/path/to/b' => 1,
            'gaubry@dv2:/path/to/my file' => 1,
        ));

        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T gaubry@dv2 /bin/bash <<EOF' . "\n" . 'rm -rf "/path/to/my file"' . "\n" . 'EOF' . "\n"))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->remove('gaubry@dv2:/path/to/my file');
        $this->assertEquals($aExpectedResult, $aResult);

        $this->assertAttributeEquals(array(
            '/path/to/my file/subdir1' => 1,
            '/path/to/a' => 2,
            '/path/to/b' => 1,
        ), '_aFileStatus', $oMockShell);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::copy
     */
    public function testCopy_ThrowExceptionWhenExecFailed ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->exactly(1))->method('exec');
        $oMockShell->expects($this->at(0))->method('exec')->will($this->throwException(new \RuntimeException('aborted!')));
        $this->setExpectedException('RuntimeException', 'aborted!');
        $oMockShell->copy('foo', 'bar', false);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::copy
     */
    public function testCopy_LocalFileToLocalDir ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')->with($this->equalTo('mkdir -p "/destpath/to/my dir"'));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo('cp -a "/srcpath/to/my file" "/destpath/to/my dir"'))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->copy('/srcpath/to/my file', '/destpath/to/my dir');
        $this->assertEquals($aExpectedResult, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::copy
     */
    public function testCopy_LocalFilesToLocalDir ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')->with($this->equalTo('mkdir -p "/destpath/to/my dir"'));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo('cp -a "/srcpath/to/"* "/destpath/to/my dir"'))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->copy('/srcpath/to/*', '/destpath/to/my dir');
        $this->assertEquals($aExpectedResult, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::copy
     */
    public function testCopy_LocalFileToLocalFile ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')->with($this->equalTo('mkdir -p "/destpath/to"'));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo('cp -a "/srcpath/to/my file" "/destpath/to/my file"'))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->copy('/srcpath/to/my file', '/destpath/to/my file', true);
        $this->assertEquals($aExpectedResult, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::copy
     */
    public function testCopy_LocalFileToRemoteDir ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')->with($this->equalTo('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T gaubry@dv2 /bin/bash <<EOF' . "\n" . 'mkdir -p "/destpath/to/my dir"' . "\n" . 'EOF' . "\n"));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo('scp -rpq "/srcpath/to/my file" "gaubry@dv2:/destpath/to/my dir"'))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->copy('/srcpath/to/my file', 'gaubry@dv2:/destpath/to/my dir');
        $this->assertEquals($aExpectedResult, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::copy
     */
    public function testCopy_RemoteFilesToLocalDir ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "' . $this->_aConfig['tmp_dir'] . '"'));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo('scp -rpq "aai-01:/path/to/a"*".css" "' . $this->_aConfig['tmp_dir'] . '"'))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->copy('aai-01:/path/to/a*.css', $this->_aConfig['tmp_dir']);
        $this->assertEquals($aExpectedResult, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::copy
     */
    public function testCopy_LocalFileToRemoteFile ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')->with($this->equalTo('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T gaubry@dv2 /bin/bash <<EOF' . "\n" . 'mkdir -p "/destpath/to"' . "\n" . 'EOF' . "\n"));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo('scp -rpq "/srcpath/to/my file" "gaubry@dv2:/destpath/to/my file"'))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->copy('/srcpath/to/my file', 'gaubry@dv2:/destpath/to/my file', true);
        $this->assertEquals($aExpectedResult, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::getPathStatus
     */
    public function testGetPathStatus_ThrowExceptionWhenExecFailed ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->exactly(1))->method('exec');
        $oMockShell->expects($this->at(0))->method('exec')->will($this->throwException(new \RuntimeException('aborted!')));
        $this->setExpectedException('RuntimeException', 'aborted!');
        $oMockShell->getPathStatus('foo');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::getPathStatus
     */
    public function testGetPathStatus_WithFile ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('[ -h "/path/to/my file" ] && echo -n 1; [ -d "/path/to/my file" ] && echo 2 || ([ -f "/path/to/my file" ] && echo 1 || echo 0)'))
            ->will($this->returnValue(array('1')));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->getPathStatus('/path/to/my file');
        $this->assertEquals(1, $aResult);
        $this->assertAttributeEquals(array('/path/to/my file' => 1), '_aFileStatus', $oMockShell);

        return $oMockShell;
    }

    /**
     * @depends testGetPathStatus_WithFile
     * @covers \GAubry\Shell\ShellAdapter::getPathStatus
     */
    public function testGetPathStatus_WithFileOnCache (ShellAdapter $oMockShell)
    {
        $this->assertAttributeEquals(array('/path/to/my file' => 1), '_aFileStatus', $oMockShell);
        $oMockShell->expects($this->never())->method('exec');
        $aResult = $oMockShell->getPathStatus('/path/to/my file');
        $this->assertEquals(1, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::getPathStatus
     */
    public function testGetPathStatus_WithDirWithoutLeadingSlash ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('[ -h "/path/to/dir" ] && echo -n 1; [ -d "/path/to/dir" ] && echo 2 || ([ -f "/path/to/dir" ] && echo 1 || echo 0)'))
            ->will($this->returnValue(array('2')));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->getPathStatus('/path/to/dir');
        $this->assertEquals(2, $aResult);

        $this->assertAttributeEquals(array('/path/to/dir' => 2), '_aFileStatus', $oMockShell);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::getPathStatus
     */
    public function testGetPathStatus_WithDirWithLeadingSlash ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('[ -h "/path/to/dir" ] && echo -n 1; [ -d "/path/to/dir" ] && echo 2 || ([ -f "/path/to/dir" ] && echo 1 || echo 0)'))
            ->will($this->returnValue(array('2')));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->getPathStatus('/path/to/dir/');
        $this->assertEquals(2, $aResult);

        $this->assertAttributeEquals(array('/path/to/dir' => 2), '_aFileStatus', $oMockShell);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::getPathStatus
     */
    public function testGetPathStatus_WithUnknown ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('[ -h "/path/to/unknown" ] && echo -n 1; [ -d "/path/to/unknown" ] && echo 2 || ([ -f "/path/to/unknown" ] && echo 1 || echo 0)'))
            ->will($this->returnValue(array('0')));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->getPathStatus('/path/to/unknown');
        $this->assertEquals(0, $aResult);

        $this->assertAttributeEquals(array(), '_aFileStatus', $oMockShell);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::getParallelSSHPathStatus
     */
    public function testGetParallelSSHPathStatus_wellFormed()
    {
        $sExpectedCmd = $this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir']
                      . '/parallelize.inc.sh "aai@aai-01 prod@aai-01"'
                      . ' "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T [] '
                      . $this->_aConfig['bash_path'] . ' <<EOF' . "\n"
                      . '[ -h \"/path/to/my file\" ] && echo -n 1; [ -d \"/path/to/my file\" ] && echo 2 || ([ -f \"/path/to/my file\" ] && echo 1 || echo 0)'
                      . "\n" . 'EOF' . "\n" . '"';
        $aReturnExec = array(
            '---[aai@aai-01]-->0|0s', '[CMD]', 'foo', '[OUT]', '1', '[ERR]', '///',
            '---[prod@aai-01]-->0|0s', '[CMD]', 'foo', '[OUT]', '0', '[ERR]', '///',
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo($sExpectedCmd))
            ->will($this->returnValue($aReturnExec));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $oMockShell->getParallelSSHPathStatus (
            '/path/to/my file',
            array('aai@aai-01', 'prod@aai-01')
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::getParallelSSHPathStatus
     */
    public function testGetParallelSSHPathStatus_With2Files ()
    {
        $aReturnExec = array(
            '---[aai@aai-01]-->0|0s', '[CMD]', 'foo', '[OUT]', '1', '[ERR]', '///',
            '---[prod@aai-01]-->0|0s', '[CMD]', 'foo', '[OUT]', '0', '[ERR]', '///',
        );
        $aExpectedParallelResult = array(
            'aai@aai-01' => 1,
            'prod@aai-01' => 0
        );
        $aExpectedStatusResult = array('aai@aai-01:/path/to/my file' => 1);

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->will($this->returnValue($aReturnExec));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->getParallelSSHPathStatus (
            '/path/to/my file',
            array('aai@aai-01', 'prod@aai-01')
        );
        $this->assertEquals($aExpectedParallelResult, $aResult);
        $this->assertAttributeEquals($aExpectedStatusResult, '_aFileStatus', $oMockShell);

        return $oMockShell;
    }

    /**
     * @depends testGetParallelSSHPathStatus_With2Files
     * @covers \GAubry\Shell\ShellAdapter::getParallelSSHPathStatus
     */
    public function testGetParallelSSHPathStatus_WithCacheAnd1File (ShellAdapter $oMockShell)
    {
        $aExpectedInitStatus = array('aai@aai-01:/path/to/my file' => 1);
        $aReturnExec = array(
            '---[prod@aai-01]-->0|0s', '[CMD]', 'foo', '[OUT]', '12', '[ERR]', '///',
        );
        $aExpectedParallelResult = array(
            'aai@aai-01' => 1,
            'prod@aai-01' => 12
        );
        $aExpectedStatusResult = array(
            'aai@aai-01:/path/to/my file' => 1,
            'prod@aai-01:/path/to/my file' => 12
        );

        $this->assertAttributeEquals($aExpectedInitStatus, '_aFileStatus', $oMockShell);
        $oMockShell->expects($this->at(0))->method('exec')
            ->will($this->returnValue($aReturnExec));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->getParallelSSHPathStatus (
            '/path/to/my file',
            array('aai@aai-01', 'prod@aai-01')
        );
        $this->assertEquals($aExpectedParallelResult, $aResult);
        $this->assertAttributeEquals($aExpectedStatusResult, '_aFileStatus', $oMockShell);

        return $oMockShell;
    }

    /**
     * @depends testGetParallelSSHPathStatus_WithCacheAnd1File
     * @covers \GAubry\Shell\ShellAdapter::getParallelSSHPathStatus
     */
    public function testGetParallelSSHPathStatus_WithOnlyCache (ShellAdapter $oMockShell)
    {
        $aExpectedInitStatus = array(
            'aai@aai-01:/path/to/my file' => 1,
            'prod@aai-01:/path/to/my file' => 12
        );
        $aExpectedParallelResult = array(
            'aai@aai-01' => 1,
            'prod@aai-01' => 12
        );
        $aExpectedStatusResult = $aExpectedInitStatus;

        $this->assertAttributeEquals($aExpectedInitStatus, '_aFileStatus', $oMockShell);
        $oMockShell->expects($this->never())->method('exec');

        $aResult = $oMockShell->getParallelSSHPathStatus (
            '/path/to/my file/',	// Le slash final est intentionnel, pour tester sa suppression automatique.
            array('aai@aai-01', 'prod@aai-01')
        );
        $this->assertEquals($aExpectedParallelResult, $aResult);
        $this->assertAttributeEquals($aExpectedStatusResult, '_aFileStatus', $oMockShell);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     */
    public function testSync_ThrowExceptionWhenExecFailed ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->exactly(1))->method('exec');
        $oMockShell->expects($this->at(0))->method('exec')->will($this->throwException(new \RuntimeException('aborted!')));
        $this->setExpectedException('RuntimeException', 'aborted!');
        $oMockShell->sync('foo', 'bar');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     * @covers \GAubry\Shell\ShellAdapter::_resumeSyncResult
     */
    public function testSync_LocalFileToLocalDir ()
    {
        $aExpectedResult = array('Number of transferred files ( / total): 2 / 1774
Total transferred file size ( / total): <1 / 61 Mio
');
        $aRawRsyncResult = array('---[-]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 64093953 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///'
        );


        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "/destpath/to/my dir"'))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "-" "rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --stats -e ssh \"/srcpath/to/my file\" \"/destpath/to/my dir\""'))
            ->will($this->returnValue($aRawRsyncResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->sync('/srcpath/to/my file', '/destpath/to/my dir');
        $this->assertEquals(
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aExpectedResult),
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aResult)
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     * @covers \GAubry\Shell\ShellAdapter::_resumeSyncResult
     */
    public function testSync_LocalFileToLocalDirInKioUnit ()
    {
        $aExpectedResult = array('Number of transferred files ( / total): 2 / 1774
Total transferred file size ( / total): <1 / 63 Kio
');
        $aRawRsyncResult = array('---[-]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 64093 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///'
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "/destpath/to/my dir"'))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "-" "rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --stats -e ssh \"/srcpath/to/my file\" \"/destpath/to/my dir\""'))
            ->will($this->returnValue($aRawRsyncResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->sync('/srcpath/to/my file', '/destpath/to/my dir');
        $this->assertEquals(
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aExpectedResult),
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aResult)
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     * @covers \GAubry\Shell\ShellAdapter::_resumeSyncResult
     */
    public function testSync_LocalFileToLocalDirInOctetUnit ()
    {
        $aExpectedResult = array('Number of transferred files ( / total): 2 / 1774
Total transferred file size ( / total): 178 / 640 o
');
        $aRawRsyncResult = array('---[-]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 640 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///'
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "/destpath/to/my dir"'))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "-" "rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --stats -e ssh \"/srcpath/to/my file\" \"/destpath/to/my dir\""'))
            ->will($this->returnValue($aRawRsyncResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->sync('/srcpath/to/my file', '/destpath/to/my dir');
        $this->assertEquals(
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aExpectedResult),
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aResult)
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     * @covers \GAubry\Shell\ShellAdapter::_resumeSyncResult
     */
    /*public function testSync_LocalFileTo2LocalDirs ()
    {
        $aExpectedResult = array(
            'Number of transferred files ( / total): 2 / 1774
Total transferred file size ( / total): <1 / 61 Mio',
        );
        $aRawRsyncResult = array('---[1]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 64093953 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///',
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "/destpath/to/my dir1"'))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo('mkdir -p "/destpath/to/my dir2"'))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(2))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "1 2" "rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --stats -e ssh \"/srcpath/to/my file\" \"/destpath/to/my dir[]\""'))
            ->will($this->returnValue($aRawRsyncResult));
        $oMockShell->expects($this->exactly(3))->method('exec');

        $aResult = $oMockShell->sync('/srcpath/to/my file', '/destpath/to/my dir[]', array('1', '2'));
        $this->assertEquals(
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aExpectedResult),
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aResult)
        );
    }*/

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     * @covers \GAubry\Shell\ShellAdapter::_resumeSyncResult
     */
    public function testSync_LocalEmptySourceToLocalDir ()
    {
        $aExpectedResult = array('Empty source directory.');
        $aRawRsyncResult = array('---[-]-->0|0s', '[CMD]', '...', '[OUT]', '[ERR]', '///');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "/destpath/to/my dir"'))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "-" "rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --stats -e ssh \"/srcpath/to/my file\" \"/destpath/to/my dir\""'))
            ->will($this->returnValue($aRawRsyncResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->sync('/srcpath/to/my file', '/destpath/to/my dir');
        $this->assertEquals(
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aExpectedResult),
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aResult)
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     * @covers \GAubry\Shell\ShellAdapter::_resumeSyncResult
     */
    public function testSync_LocalFilesToLocalDir ()
    {
        $aExpectedResult = array(
            'Number of transferred files ( / total): 2 / 1774
Total transferred file size ( / total): <1 / 61 Mio',
        );
        $aRawRsyncResult = array('---[-]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 64093953 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///',
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "/destpath/to/my dir"'))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "-" "if ls -1 \"/srcpath/to/my files\" | grep -q .; then rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --stats -e ssh \"/srcpath/to/my files/\"* \"/destpath/to/my dir\"; fi"'))
            ->will($this->returnValue($aRawRsyncResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->sync('/srcpath/to/my files/*', '/destpath/to/my dir');
        $this->assertEquals(
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aExpectedResult),
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aResult)
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     * @covers \GAubry\Shell\ShellAdapter::_resumeSyncResult
     */
    public function testSync_LocalFilesToLocalDirWithLeadingSlash ()
    {
        $aExpectedResult = array(
            'Number of transferred files ( / total): 2 / 1774
Total transferred file size ( / total): <1 / 61 Mio',
        );
        $aRawRsyncResult = array('---[-]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 64093953 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///',
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "/destpath/to/my dir"'))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "-" "rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --stats -e ssh \"/srcpath/to/my files/\" \"/destpath/to/my dir\""'))
            ->will($this->returnValue($aRawRsyncResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->sync('/srcpath/to/my files/', '/destpath/to/my dir');
        $this->assertEquals(
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aExpectedResult),
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aResult)
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     * @covers \GAubry\Shell\ShellAdapter::_resumeSyncResult
     */
    public function testSync_LocalFileToLocalDirWithAdditionalExclude ()
    {
        $aExpectedResult = array('Number of transferred files ( / total): 2 / 1774
Total transferred file size ( / total): <1 / 61 Mio
');
        $aRawRsyncResult = array('---[-]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 64093953 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///'
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "/destpath/to/my dir"'))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "-" "rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --exclude=\"toto\" --exclude=\"titi\" --stats -e ssh \"/srcpath/to/my file\" \"/destpath/to/my dir\""'))
            ->will($this->returnValue($aRawRsyncResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->sync('/srcpath/to/my file', '/destpath/to/my dir', array(), array(), array('toto', 'titi', 'toto', '.bzr/'));
        $this->assertEquals(
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aExpectedResult),
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aResult)
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     * @covers \GAubry\Shell\ShellAdapter::_resumeSyncResult
     */
    public function testSync_LocalFileToLocalDirWithSimpleInclude ()
    {
        $aExpectedResult = array('Number of transferred files ( / total): 2 / 1774
Total transferred file size ( / total): <1 / 61 Mio
');
        $aRawRsyncResult = array('---[-]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 64093953 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///'
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "/destpath/to/my dir"'))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "-" "rsync -axz --delete --include=\"*.js\" --include=\"*.css\" --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --exclude=\"*\" --stats -e ssh \"/srcpath/to/my file\" \"/destpath/to/my dir\""'))
            ->will($this->returnValue($aRawRsyncResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->sync('/srcpath/to/my file', '/destpath/to/my dir', array(), array('*.js', '*.css'), array('*'));
        $this->assertEquals(
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aExpectedResult),
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aResult)
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     * @covers \GAubry\Shell\ShellAdapter::_resumeSyncResult
     */
    public function testSync_LocalFileToRemotesDir ()
    {
        $aExpectedResult = array(
            'Server: server1 (~0s)
Number of transferred files ( / total): 2 / 1774
Total transferred file size ( / total): <1 / 61 Mio',
            'Server: login@server2 (~0s)
Number of transferred files ( / total): 2 / 177
Total transferred file size ( / total): <1 / 626 Kio',
        );
        $aMkdirExecResult = array(
            '---[server1]-->0|0s', '[CMD]', '...', '[OUT]', '[ERR]', '///',
            '---[login@server2]-->0|0s', '[CMD]', '...', '[OUT]', '[ERR]', '///'
        );
        $aRawRsyncResult = array('---[server1]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 64093953 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///',

            '---[login@server2]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 177',
            'Number of files transferred: 2',
            'Total file size: 640939 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///'
        );

        $sCmd = $this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir']
              . '/parallelize.inc.sh "server1 login@server2" "rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --stats -e ssh \"/srcpath/to/my file\" \"[]:/destpath/to/my dir\""';

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "server1 login@server2" "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T [] /bin/bash <<EOF' . "\n"
                . 'mkdir -p \"/destpath/to/my dir\"' . "\n"
                . 'EOF' . "\n" . '"'))
            ->will($this->returnValue($aMkdirExecResult));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($sCmd))
            ->will($this->returnValue($aRawRsyncResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->sync('/srcpath/to/my file', '[]:/destpath/to/my dir', array('server1', 'login@server2'));

        $this->assertEquals(
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aExpectedResult),
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aResult)
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     */
    public function testSync_RemoteDirToRemoteDirWithSameHost ()
    {
        $aExecResult = array('---[-]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 64093953 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///'
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T user@server /bin/bash <<EOF' . "\n"
                . 'mkdir -p "/destpath/to/my dir"' . "\n" . 'EOF' . "\n"))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' '
                . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "-" "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T user@server /bin/bash <<EOF' . "\n"
                . 'rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --exclude=\"smarty/*/wrt*\" --exclude=\"smarty/**/wrt*\" --stats -e ssh \"/srcpath/to/my dir\" \"/destpath/to/my dir\"' . "\n"
                . 'EOF' . "\n" . '"'))
            ->will($this->returnValue($aExecResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $oMockShell->sync('user@server:/srcpath/to/my dir', 'user@server:/destpath/to/my dir', array(), array(), array('smarty/*/wrt*', 'smarty/**/wrt*'));
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     */
    public function testSync_RemoteDirToRemoteDirWithDifferentHost ()
    {
        $aExecResult = array('---[-]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 64093953 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///'
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T server2 /bin/bash <<EOF' . "\n"
                . 'mkdir -p "/destpath/to/my dir"' . "\n" . 'EOF' . "\n"))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' '
                . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "-" "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T user@server1 /bin/bash <<EOF' . "\n"
                . 'rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --stats -e ssh \"/srcpath/to/my dir\" \"server2:/destpath/to/my dir\"' . "\n"
                . 'EOF' . "\n" . '"'))
            ->will($this->returnValue($aExecResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $oMockShell->sync('user@server1:/srcpath/to/my dir', 'server2:/destpath/to/my dir');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     */
    public function testSync_RemoteDirToLocalDir ()
    {
        $aExecResult = array('---[-]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 64093953 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///'
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "/destpath/to/my dir"'))
            ->will($this->returnValue(array()));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "-" "rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --stats -e ssh \"user@server1:/srcpath/to/my dir\" \"/destpath/to/my dir\""'))
            ->will($this->returnValue($aExecResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $oMockShell->sync('user@server1:/srcpath/to/my dir', '/destpath/to/my dir');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     */
    public function testSync_RemoteDirToMultiRemotesDirWithDifferentHost ()
    {
        $aExpectedResult = array(
            'Server: aai-01 (~0s)
Number of transferred files ( / total): 2 / 1774
Total transferred file size ( / total): <1 / 61 Mio',
            'Server: aai@aai-02 (~0s)
Number of transferred files ( / total): 2 / 177
Total transferred file size ( / total): <1 / 6 Kio',
        );
        $aMkdirExecResult = array(
            '---[aai-01]-->0|0s', '[CMD]', '...', '[OUT]', '[ERR]', '///',
            '---[aai@aai-02]-->0|0s', '[CMD]', '...', '[OUT]', '[ERR]', '///'
        );
        $aExecResult = array('---[aai-01]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 1774',
            'Number of files transferred: 2',
            'Total file size: 64093953 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///',

            '---[aai@aai-02]-->0|0s', '[CMD]', '...', '[OUT]',
            'Number of files: 177',
            'Number of files transferred: 2',
            'Total file size: 6409 bytes',
            'Total transferred file size: 178 bytes',
            'Literal data: 178 bytes',
            'Matched data: 0 bytes',
            'File list size: 39177',
            'File list generation time: 0.013 seconds',
            'File list transfer time: 0.000 seconds',
            'Total bytes sent: 39542',
            'Total bytes received: 64',
            '',
            'sent 39542 bytes  received 64 bytes  26404.00 bytes/sec',
            'total size is 64093953  speedup is 1618.29',
            '[ERR]', '///',
        );

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' '
                . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "aai-01 aai@aai-02" "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T [] /bin/bash <<EOF' . "\n"
                . 'mkdir -p \"/destpath/to/my dir\"' . "\n"
                . 'EOF' . "\n" . '"'))
            ->will($this->returnValue($aMkdirExecResult));
        $oMockShell->expects($this->at(1))->method('exec')
            ->with($this->equalTo($this->_aConfig['bash_path'] . ' ' . $this->_aConfig['lib_dir'] . '/parallelize.inc.sh "aai-01 aai@aai-02" "ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T user@server1 /bin/bash <<EOF' . "\n"
                . 'rsync -axz --delete --exclude=\".bzr/\" --exclude=\".cvsignore\" --exclude=\".git/\" --exclude=\".gitignore\" --exclude=\".svn/\" --exclude=\"cvslog.*\" --exclude=\"CVS\" --exclude=\"CVS.adm\" --stats -e ssh \"/srcpath/to/my dir\" \"[]:/destpath/to/my dir\"' . "\n"
                . 'EOF' . "\n" . '"'))
            ->will($this->returnValue($aExecResult));
        $oMockShell->expects($this->exactly(2))->method('exec');

        $aResult = $oMockShell->sync('user@server1:/srcpath/to/my dir', '[]:/destpath/to/my dir', array('aai-01', 'aai@aai-02'));

        $this->assertEquals(
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aExpectedResult),
            array_map(function($s){return preg_replace('/\s/', '', $s);}, $aResult)
        );
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::sync
     */
    /*public function testSyncRemoteDirToLocalDirsThrowException () {
        $this->setExpectedException('RuntimeException', 'Not yet implemented!');
        $this->oShell->sync('user@server1:/srcpath/to/my dir', '/destpath/to/my dir[]', array('1', '2'));
    }*/

    /**
     * @covers \GAubry\Shell\ShellAdapter::createLink
     */
    public function testCreateLink_ThrowExceptionWhenExecFailed ()
    {
        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->exactly(1))->method('exec');
        $oMockShell->expects($this->at(0))->method('exec')->will($this->throwException(new \RuntimeException('aborted!')));
        $this->setExpectedException('RuntimeException', 'aborted!');
        $oMockShell->createLink('foo', 'bar');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::createLink
     */
    public function testCreateLink_ThrowExceptionWhenDifferentHosts1 ()
    {
        $this->setExpectedException(
            'DomainException',
            "Hosts must be equals. Link='/foo'. Target='server:/bar'."
        );
        $this->oShell->createLink('/foo', 'server:/bar');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::createLink
     */
    public function testCreateLink_ThrowExceptionWhenDifferentHosts2 ()
    {
        $this->setExpectedException(
            'DomainException',
            "Hosts must be equals. Link='user@server:/foo'. Target='/bar'."
        );
        $this->oShell->createLink('user@server:/foo', '/bar');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::createLink
     */
    public function testCreateLink_ThrowExceptionWhenDifferentHosts3 ()
    {
        $this->setExpectedException(
            'DomainException',
            "Hosts must be equals. Link='server1:/foo'. Target='server2:/bar'."
        );
        $this->oShell->createLink('server1:/foo', 'server2:/bar');
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::createLink
     */
    public function testCreateLink_WithLocalPath ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('mkdir -p "$(dirname "/path/to/my file")" && ln -snf "/path/to/my target" "/path/to/my file"'))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->createLink('/path/to/my file', '/path/to/my target');
        $this->assertEquals($aExpectedResult, $aResult);
    }

    /**
     * @covers \GAubry\Shell\ShellAdapter::createLink
     */
    public function testCreateLink_WithRemotePath ()
    {
        $aExpectedResult = array('blabla');

        $oMockShell = $this->getMock('\GAubry\Shell\ShellAdapter', array('exec'), array($this->oLogger, $this->_aConfig));
        $oMockShell->expects($this->at(0))->method('exec')
            ->with($this->equalTo('ssh -o StrictHostKeyChecking=no -o ConnectTimeout=10 -o BatchMode=yes -T gaubry@dv2 /bin/bash <<EOF' . "\n" . 'mkdir -p "$(dirname "/path/to/my file")" && ln -snf "/path/to/my target" "/path/to/my file"' . "\n" . 'EOF' . "\n"))
            ->will($this->returnValue($aExpectedResult));
        $oMockShell->expects($this->exactly(1))->method('exec');

        $aResult = $oMockShell->createLink('gaubry@dv2:/path/to/my file', 'gaubry@dv2:/path/to/my target');
        $this->assertEquals($aExpectedResult, $aResult);
    }
}