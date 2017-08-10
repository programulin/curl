<?php
namespace Programulin;

/**
 * Класс облегчает работу с Curl.
 * Для каждого сайта нужно создавать отдельный экземпляр класса.
 * Поддерживается keep-alive.
 */
class Curl
{

    /** @var resource Ресурс, созданный через Curl */
    private $curl;

    /** @var string Базовый URL */
    private $host;

    /** @var array Заголовки запроса */
    private $headers;

    /**
     * Создание объекта для последующего парсинга данных с конкретного сайта.
     *
     * @param string $host URL сайта, с которого будут парситься данные. Пример: 'http://you-com.ru/'.
     * Протокол обязателен, последний слеш не обязателен.
     */
    public function url($host)
    {
        $this->curl = curl_init();
        $this->host = rtrim($host, '/');

        $this->set(CURLOPT_RETURNTRANSFER, true);
        $this->set(CURLOPT_HEADER, true);

        $this->headers = [
            'Host: ' . parse_url($host, PHP_URL_HOST),
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
            'Connection: keep-alive'
        ];

        return $this;
    }

    /** Автоматическое закрытие соединения */
    public function __destruct()
    {
        if($this->curl)
            curl_close($this->curl);
    }

    /**
     * Установка параметра в curl_setopt
     * 
     * @param mixed $name Название параметра. Предполагается подстановка Curl-констант, например CURLOPT_URL.
     * @param mixed $value Значение параметра.
     */
    private function set($name, $value)
    {
        curl_setopt($this->curl, $name, $value);
    }

    /**
     * Выполнение запроса.
     * 
     * @param string $url Ссылка относительно базового URL, указываемого при создании объекта.
     * Пример: '/admin_main/'. Первый слеш не обязателен.
     * @param boolean $debug Если false (по-умолчанию) - результатом будет строка с контентом.
     * Если true - результатом будет массив из следующих элементов:
     * $result['body'] - сам контент;
     * $result['info'] - результат функции curl_getinfo();
     * $result['headers'] - массив с заголовками (если Curl переходил по редиректам,
     * массив будет содержать подмассивы с заголовками каждой страницы).
     * @return string/array Базовая или расширенная информация в зависимости от параметра $debug. 
     */
    public function request($url = '', $debug = null)
    {
        // Установка URL и заголовков
        $full_url = $this->host . '/' . ltrim($url, '/');
        $this->set(CURLOPT_URL, $full_url);

        $this->set(CURLOPT_HTTPHEADER, $this->headers);

        // Выполнение запроса и получение информации
        $data = curl_exec($this->curl);

        $result['info'] = curl_getinfo($this->curl);

        // Получение спарсенного контента
        $result['body'] = substr($data, $result['info']['header_size']);

        if (!$debug)
            return $result['body'];

        // Получение заголовков в виде текста
        $headers = substr($data, 0, $result['info']['header_size']);

        $i = 1;

        // Превращение строки с загловками в массив
        foreach (explode("\n", $headers) as $header)
        {
            $header = trim($header);

            /*
              Если Curl следует за редиректами, он сохраняет в кучу заголовки всех ответов сервера.
              Этот код разделяет заголовки, для каждой страницы - свой массив заголовков.
             */
            if (empty($header))
            {
                $i++;
                continue;
            }

            $arr = explode(':', $header, 2);

            if (isset($arr[1]))
                $result['headers'][$i][$arr[0]] = trim($arr[1]); // Удаление \r и пробела
            else
                $result['headers'][$i]['status'] = trim($arr[0]);
        }

        return $result;
    }

    /**
     * Проверка сертификатов
     * 
     * @param bool $status Проверять сертификаты (да/нет)
     * @return $this
     */
    public function ssl($status)
    {
        $this->set(CURLOPT_SSL_VERIFYPEER, $status);
        $this->set(CURLOPT_SSL_VERIFYHOST, $status);
        return $this;
    }

    /**
     * Установка метода отправки и отправляемых данных
     * 
     * @param bool $status Метод отправки (true - POST, false - GET).
     * @param array $data Для метода POST - массив отправляемых данных.
     * @return $this
     */
    public function post($status, array $data = null)
    {
        if ($status)
        {
            $this->set(CURLOPT_POST, $status);

            if (!is_null($data))
                $this->set(CURLOPT_POSTFIELDS, http_build_query($data));
        }
        else
        {
            $this->set(CURLOPT_POSTFIELDS, null);
            $this->set(CURLOPT_POST, false);
            $this->set(CURLOPT_HTTPGET, true);
        }

        return $this;
    }

    /**
     * Установка файла с куками.
     * 
     * @param string $file Абсолютный путь к файлу с куками (относительный не сработает!)
     * @return $this
     */
    public function cookie($file)
    {
        $this->set(CURLOPT_COOKIEJAR, $file);
        $this->set(CURLOPT_COOKIEFILE, $file);
        return $this;
    }

    /**
     * Установка заголовка.
     * 
     * @param string $header Строка с заголовком.
     * @return $this
     */
    public function header($header)
    {
        $this->headers[] = $header;
        return $this;
    }

    /**
     * Установка массива заголовков.
     * 
     * @param array $headers Массив заголовков
     * @return $this
     */
    public function headers(array $headers)
    {
        foreach ($headers as $header)
            $this->headers[] = $header;

        return $this;
    }

    /**
     * Установка заголовка с реферером.
     * 
     * @param string $referer Ссылка.
     * @return $this
     */
    public function referer($referer)
    {
        $this->headers[] = 'Referer: ' . $referer;
        return $this;
    }

    /**
     * Установка заголовка с браузером.
     * 
     * @param string $agent Браузер.
     * @return $this
     */
    public function agent($agent)
    {
        $this->headers[] = 'User-Agent: ' . $agent;
        return $this;
    }

    /**
     * Переход по редиректам.
     * 
     * @param int $count Разрешённое количестово переходов по редиректам.
     * @return $this
     */
    public function follow($count)
    {
        $this->set(CURLOPT_FOLLOWLOCATION, $count);
        return $this;
    }

    /**
     * Установка максимального времени ожидания ответа.
     * 
     * @param int $seconds Макс. время ожидания в секундах.
     * @return $this
     */
    public function timeout($seconds)
    {
        $this->set(CURLOPT_CONNECTTIMEOUT, $seconds);
        return $this;
    }
}