<?php
namespace Kwf\FileWatcher\Backend;
use Kwf\FileWatcher\Event\Delete as DeleteEvent;
use Kwf\FileWatcher\Event\Create as CreateEvent;
use Kwf\FileWatcher\Event\Modify as ModifyEvent;
use Kwf\FileWatcher\Event\Move as MoveEvent;
use Kwf\FileWatcher\Helper\Links as LinksHelper;

class Watchmedo extends ChildProcessAbstract
{
    public function isAvailable()
    {
        exec("watchmedo --version 2>&1", $out, $ret);
        return $ret == 0;
    }

    protected function _getCmd()
    {
        $exclude = $this->_excludePatterns;
        foreach ($exclude as &$e) {
            $e = '*'.$e;
        }
        $cmd = "watchmedo log --recursive --ignore-directories ";
        if ($exclude) $cmd .= "--ignore-patterns ".escapeshellarg(implode(';', $exclude)).' ';


        $paths = $this->_paths;
        if ($this->_followLinks) {
            //watchmedo doesn't recurse into symlinks
            //so we add all symlinks to $paths
            $paths = LinksHelper::followLinks($paths, $this->_excludePatterns);
        }

        $cmd .= implode(' ', $paths);
        if (strtoupper(substr(PHP_OS, 0, 3)) !== 'WIN') {
            //disble output bufferering
            $cmd = "PYTHONUNBUFFERED=1 $cmd";
        } else {
            //on windows disable output buffering using -u
            //the above doesn't work
            $cmd = "python -u -m watchdog.$cmd";
        }
        return $cmd;
    }

    protected function _getEventFromLine($line)
    {
        if (!preg_match('#^on_([a-z]+)\(.*event=.*src_path=u?\'([^\']+)\'(, dest_path=u?\'([^\']+)\')?#', trim($line), $m)) {
            $this->_logger->error("unknown event: $line");
            return;
        }
        $ev = $m[1];
        $file = str_replace('\\\\', '/', $m[2]); //windows
        if ($ev == 'modified') {
            return new ModifyEvent($file);
        } else if ($ev == 'created') {
            return new CreateEvent($file);
        } else if ($ev == 'deleted') {
            return new DeleteEvent($file);
        } else if ($ev == 'moved') {
            $m[4] = str_replace('\\\\', '/', $m[4]);
            $dest = $m[4];
            return new MoveEvent($file, $dest);
        }
    }
}
