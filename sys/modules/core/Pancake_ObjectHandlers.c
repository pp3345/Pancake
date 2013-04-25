
	/****************************************************************/
    /* Pancake                                                      */
    /* Pancake_ObjectHandlers.c                                     */
    /* 2012 - 2013 Yussuf Khalil                                    */
    /* License: http://pancakehttp.net/license/                     */
    /****************************************************************/

#include "Pancake.h"

zend_object_handlers PancakeFastObjectHandlers = {
	zend_objects_store_add_ref,				/* add_ref */
	zend_objects_store_del_ref,				/* del_ref */
	zend_objects_clone_obj,					/* clone_obj */

	PancakeFastReadProperty,				/* read_property */
	PancakeFastWriteProperty,				/* write_property */
	NULL,				/* read_dimension */
	NULL,									/* write_dimension */
	PancakeFastGetPropertyPtrPtr,									/* get_property_ptr_ptr */
	NULL,									/* get */
	NULL,									/* set */
	PancakeFastHasProperty,					/* has_property */
	NULL,				/* unset_property */
	NULL,					/* has_dimension */
	NULL,				/* unset_dimension */
	zend_std_get_properties,				/* get_properties */
	PancakeFastObjectGetMethod,					/* get_method */
	NULL,									/* call_method */
	zend_std_get_constructor,				/* get_constructor */
	PancakeObjectGetClass,				/* get_class_entry */
	NULL,			/* get_class_name */
	NULL,				/* compare_objects */
	zend_std_cast_object_tostring,			/* cast_object */
	NULL,									/* count_elements */
	NULL,									/* get_debug_info */
	NULL,					/* get_closure */
	NULL,						/* get_gc */
};

zend_object_value PancakeCreateObject(zend_class_entry *classType TSRMLS_DC) {
	zend_object_value retval;
	zend_object *object;

	object = emalloc(sizeof(zend_object));
	object->ce = classType;
	object->properties = NULL;
	object->properties_table = NULL;
	object->guards = NULL;
	retval.handle = zend_objects_store_put(object, (zend_objects_store_dtor_t) zend_objects_destroy_object, (zend_objects_free_object_storage_t) zend_objects_free_object_storage, NULL TSRMLS_CC);
	retval.handlers = &PancakeFastObjectHandlers;

	object_properties_init(object, classType);

	return retval;
}

zval **PancakeFastGetPropertyPtrPtr(zval *object, zval *member, const struct _zend_literal *key TSRMLS_DC) {
	zend_object *zobj = Z_OBJ_P(object);
	zend_property_info *property_info;
	zval **retval;

	if(UNEXPECTED(key == NULL)) {
		zend_hash_find(&zobj->ce->properties_info, Z_STRVAL_P(member), Z_STRLEN_P(member) + 1, (void**) &property_info);
	} else if(UNEXPECTED((property_info = CACHED_POLYMORPHIC_PTR(key->cache_slot, zobj->ce)) == NULL)) {
		zend_hash_quick_find(&zobj->ce->properties_info, Z_STRVAL_P(member), Z_STRLEN_P(member) + 1, key->hash_value, (void**) &property_info);
		CACHE_POLYMORPHIC_PTR(key->cache_slot, zobj->ce, property_info);
	}

	if(zobj->properties
		? ((retval = (zval**)zobj->properties_table[property_info->offset]) == NULL)
		: (*(retval = &zobj->properties_table[property_info->offset]) == NULL)) {
		zval *new = &EG(uninitialized_zval);
		Z_ADDREF_P(new);

		if (!zobj->properties) {
			zobj->properties_table[property_info->offset] = new;
			retval = &zobj->properties_table[property_info->offset];
		} else if (zobj->properties_table[property_info->offset]) {
			*(zval**) zobj->properties_table[property_info->offset] = new;
			retval = (zval**) zobj->properties_table[property_info->offset];
		} else {
			zend_hash_quick_update(zobj->properties, property_info->name, property_info->name_length+1, property_info->h, &new, sizeof(zval*), (void**) &zobj->properties_table[property_info->offset]);
			retval = (zval**) zobj->properties_table[property_info->offset];
		}
	}

	return retval;
}

zval *PancakeFastReadProperty(zval *object, zval *member, ulong hashValue, const zend_literal *key TSRMLS_DC) {
	zend_object *zobj = Z_OBJ_P(object);
	zend_property_info *property_info;
	zval **retval;

	if(UNEXPECTED(key == NULL && hashValue <= BP_VAR_UNSET)) {
		zend_hash_find(&zobj->ce->properties_info, Z_STRVAL_P(member), Z_STRLEN_P(member) + 1, (void**) &property_info);
	} else if(key) {
		if(UNEXPECTED((property_info = CACHED_POLYMORPHIC_PTR(key->cache_slot, zobj->ce)) == NULL)) {
			zend_hash_quick_find(&zobj->ce->properties_info, Z_STRVAL_P(member), Z_STRLEN_P(member) + 1, key->hash_value, (void**) &property_info);
			CACHE_POLYMORPHIC_PTR(key->cache_slot, zobj->ce, property_info);
		}
	} else {
		zend_hash_quick_find(&zobj->ce->properties_info, Z_STRVAL_P(member), Z_STRLEN_P(member) + 1, hashValue, (void**) &property_info);
	}

	if(zobj->properties) {
		retval = (zval**) zobj->properties_table[property_info->offset];
	} else {
		retval = &zobj->properties_table[property_info->offset];
	}

	return *retval;
}

void PancakeFastWriteProperty(zval *object, zval *member, zval *value, const zend_literal *key TSRMLS_DC) {
	zend_object *zobj = Z_OBJ_P(object);
	zval **variable_ptr;
	zend_property_info *property_info;

	if(UNEXPECTED(key == NULL)) {
		zend_hash_find(&zobj->ce->properties_info, Z_STRVAL_P(member), Z_STRLEN_P(member) + 1, (void**) &property_info);
	} else if(UNEXPECTED((property_info = CACHED_POLYMORPHIC_PTR(key->cache_slot, zobj->ce)) == NULL)) {
		zend_hash_quick_find(&zobj->ce->properties_info, Z_STRVAL_P(member), Z_STRLEN_P(member) + 1, key->hash_value, (void**) &property_info);
		CACHE_POLYMORPHIC_PTR(key->cache_slot, zobj->ce, property_info);
	}

	if(zobj->properties) {
		variable_ptr = (zval**) zobj->properties_table[property_info->offset];
	} else {
		variable_ptr = &zobj->properties_table[property_info->offset];
	}

	if (EXPECTED(*variable_ptr != value)) {
		if (UNEXPECTED(PZVAL_IS_REF(*variable_ptr))) {
			zval garbage = **variable_ptr;

			Z_TYPE_PP(variable_ptr) = Z_TYPE_P(value);
			(*variable_ptr)->value = value->value;
			if (Z_REFCOUNT_P(value) > 0) {
				zval_copy_ctor(*variable_ptr);
			}
			zval_dtor(&garbage);
		} else {
			zval *garbage = *variable_ptr;

			Z_ADDREF_P(value);
			if (PZVAL_IS_REF(value)) {
				SEPARATE_ZVAL(&value);
			}
			*variable_ptr = value;
			zval_ptr_dtor(&garbage);
		}
	}
}

void PancakeQuickWriteProperty(zval *object, zval *value, char *name, int name_len, ulong h TSRMLS_DC) {
	zend_object *zobj = Z_OBJ_P(object);
	zval **variable_ptr;
	zend_property_info *property_info;

	zend_hash_quick_find(&zobj->ce->properties_info, name, name_len, h, (void**) &property_info);

	if(zobj->properties) {
		variable_ptr = (zval**) zobj->properties_table[property_info->offset];
	} else {
		variable_ptr = &zobj->properties_table[property_info->offset];
	}

	if (EXPECTED(*variable_ptr != value)) {
		if (UNEXPECTED(PZVAL_IS_REF(*variable_ptr))) {
			zval garbage = **variable_ptr;

			Z_TYPE_PP(variable_ptr) = Z_TYPE_P(value);
			(*variable_ptr)->value = value->value;
			if (Z_REFCOUNT_P(value) > 0) {
				zval_copy_ctor(*variable_ptr);
			}
			zval_dtor(&garbage);
		} else {
			zval *garbage = *variable_ptr;

			Z_ADDREF_P(value);
			if (PZVAL_IS_REF(value)) {
				SEPARATE_ZVAL(&value);
			}
			*variable_ptr = value;
			zval_ptr_dtor(&garbage);
		}
	}
}

zend_class_entry *PancakeObjectGetClass(const zval *object TSRMLS_DC) {
	return Z_OBJ_P(object)->ce;
}

static int PancakeFastHasProperty(zval *object, zval *member, int has_set_exists, const zend_literal *key TSRMLS_DC) {
	zend_object *zobj = Z_OBJ_P(object);
	zend_property_info *property_info = NULL;

	if(UNEXPECTED(key == NULL)) {
		zend_hash_find(&zobj->ce->properties_info, Z_STRVAL_P(member), Z_STRLEN_P(member) + 1, (void**) &property_info);
	} else if(UNEXPECTED((property_info = CACHED_POLYMORPHIC_PTR(key->cache_slot, zobj->ce)) == NULL)) {
		zend_hash_quick_find(&zobj->ce->properties_info, Z_STRVAL_P(member), Z_STRLEN_P(member) + 1, key->hash_value, (void**) &property_info);
		CACHE_POLYMORPHIC_PTR(key->cache_slot, zobj->ce, property_info);
	}

	if(UNEXPECTED(property_info == NULL)) {
		return 0;
	}

	return 1;
}

static union _zend_function *PancakeFastObjectGetMethod(zval **object_ptr, char *method_name, int method_len, const zend_literal *key TSRMLS_DC) {
	zend_function *fbc;
	zval *object = *object_ptr;
	zend_object *zobj = Z_OBJ_P(object);
	ulong hash_value;
	char *lc_method_name;
	ALLOCA_FLAG(use_heap)

	if (EXPECTED(key != NULL)) {
		lc_method_name = Z_STRVAL(key->constant);
		hash_value = key->hash_value;
	} else {
		lc_method_name = do_alloca(method_len + 1, use_heap);
		/* Create a zend_copy_str_tolower(dest, src, src_length); */
		zend_str_tolower_copy(lc_method_name, method_name, method_len);
		hash_value = zend_hash_func(lc_method_name, method_len + 1);
	}

	if (UNEXPECTED(zend_hash_quick_find(&zobj->ce->function_table, lc_method_name, method_len + 1, hash_value, (void**) &fbc) == FAILURE)) {
		if (UNEXPECTED(!key)) {
			free_alloca(lc_method_name, use_heap);
		}

		return NULL;
	}

	if (UNEXPECTED(!key)) {
		free_alloca(lc_method_name, use_heap);
	}

	return fbc;
}

PHP_FUNCTION(makeFastClass) {
	char *className;
	int className_len;
	zend_class_entry **ce;

	if(UNEXPECTED(zend_parse_parameters(ZEND_NUM_ARGS() TSRMLS_CC, "s", &className, &className_len) == FAILURE)) {
		RETURN_FALSE;
	}

	if(UNEXPECTED(zend_lookup_class(className, className_len, &ce TSRMLS_CC) == FAILURE)) {
		zend_error(E_WARNING, "Class %s not found", className);
		RETURN_FALSE;
	}

	(*ce)->create_object = PancakeCreateObject;

	RETURN_TRUE;
}
