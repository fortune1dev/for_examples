/**
 *
 * Функция форматирует число, разделяя тысячи пробелами
 *
 * @param {number} num - число для форматирования
 * @returns string
 */
function toSpacedNumbers(num) {
    return num.toString().replace(/(.)(?=(\d{3})+$)/g, '$1 ');
}

/**
 *
 * Функция приводит число к сокращенному виду, заменяя
 * тысячи на К, миллионы на М, миллиарды на В.
 * Символ отделяется пробелом.
 * Разелитель "после запятой" - согласно языковым настройкам.
 *
 * @param {number} num - число для форматирования
 * @param {number} precision - точность после запятой, по умолчинию = 1
 * @returns string
 */
function toShortString(num, precision = 1) {
    const sChar = ['', 'K', 'M', 'B'];
    const ngroups = num.toString().match(/\d{1,3}/g);

    return (num / 1000 ** (ngroups.length - 1)).toFixed(precision) + ' ' + sChar[ngroups.length - 1];
}
