
	/****************************************************************/
    /* Pancake                                                      */
    /* Pancake_MIME.c                                      			*/
    /* 2012 Yussuf Khalil                                           */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "php_PancakeBase.h"

PANCAKE_API zval *PancakeMIMEType(char *filePath, int filePath_len TSRMLS_DC) {
	zval **mimeType;
	char *filePath_dupe = estrndup(filePath, filePath_len);

	char *ext = strrchr(filePath_dupe, '.');

	if(ext != NULL) {
		ext++;
		php_strtolower(ext, strlen(ext));
	}

	if(ext == NULL)
		ext = filePath;

	if(zend_hash_find(PANCAKE_GLOBALS(mimeTable), ext, strlen(ext), (void**) &mimeType) == SUCCESS) {
		efree(filePath_dupe);
		return *mimeType;
	}

	efree(filePath_dupe);
	return PANCAKE_GLOBALS(defaultMimeType);
}

PHP_METHOD(MIME, typeOf) {
	char *filePath;
	int filePath_len;

	if(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &filePath, &filePath_len) == FAILURE) {
		RETURN_FALSE;
	}

	zval *result = PancakeMIMEType(filePath, filePath_len TSRMLS_CC);

	RETURN_ZVAL(result, 1, 0);
}

PHP_METHOD(MIME, load) {
	zval *array, *arg, retval;

	/* Fetch allowHEAD */
	MAKE_STD_ZVAL(array);
	array_init(array);
	add_next_index_string(array, "Pancake\\Config", 1);
	add_next_index_string(array, "get", 1);

	MAKE_STD_ZVAL(arg);
	arg->type = IS_STRING;
	arg->value.str.val = estrdup("mime");
	arg->value.str.len = strlen("mime");

	call_user_function(CG(function_table), NULL, array, &retval, 1, &arg);

	if(Z_TYPE(retval) != IS_ARRAY) {
		zend_error(E_ERROR, "Bad MIME type array - Please check Pancake MIME type configuration");
	}

	ALLOC_HASHTABLE(PANCAKE_GLOBALS(mimeTable));
	zend_hash_init(PANCAKE_GLOBALS(mimeTable), 0, NULL, ZVAL_PTR_DTOR, 0);

	zval **data, **ext;
	char *key;

	for(zend_hash_internal_pointer_reset(Z_ARRVAL(retval));
		zend_hash_get_current_data(Z_ARRVAL(retval), (void**) &data) == SUCCESS &&
		zend_hash_get_current_key(Z_ARRVAL(retval), &key, NULL, 0) == HASH_KEY_IS_STRING;
		zend_hash_move_forward(Z_ARRVAL(retval))) {

		for(zend_hash_internal_pointer_reset(Z_ARRVAL_PP(data));
			zend_hash_get_current_data(Z_ARRVAL_PP(data), (void**) &ext) == SUCCESS;
			zend_hash_move_forward(Z_ARRVAL_PP(data))) {

			zval *zkey;
			MAKE_STD_ZVAL(zkey);
			zkey->type = IS_STRING;
			zkey->value.str.val = estrdup(key);
			zkey->value.str.len = strlen(key);

			zend_hash_add(PANCAKE_GLOBALS(mimeTable), Z_STRVAL_PP(ext), Z_STRLEN_PP(ext), (void*) &zkey, sizeof(zval*), NULL);
		}
	}

	MAKE_STD_ZVAL(PANCAKE_GLOBALS(defaultMimeType));
	PANCAKE_GLOBALS(defaultMimeType)->type = IS_STRING;
	PANCAKE_GLOBALS(defaultMimeType)->value.str.val = estrdup("application/octet-stream");
	PANCAKE_GLOBALS(defaultMimeType)->value.str.len = strlen("application/octet-stream");

	free:
	zval_dtor(&retval);
	zval_dtor(array);
	zval_dtor(arg);
	efree(array);
	efree(arg);
}
